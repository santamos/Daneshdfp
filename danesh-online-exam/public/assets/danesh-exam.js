(() => {
    const SELECTOR = '.danesh-exam';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_IN_PROGRESS = 'in_progress';

    const formatSeconds = (value) => {
        if (value === null || typeof value === 'undefined') {
            return '—';
        }

        const seconds = Math.max(0, Math.floor(value));
        const minutes = Math.floor(seconds / 60);
        const remaining = seconds % 60;

        return `${minutes}:${remaining.toString().padStart(2, '0')}`;
    };

    const safeJsonParse = (value, fallback = null) => {
        try {
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    };

    const createEl = (tag, className, text) => {
        const el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        if (text) {
            el.textContent = text;
        }

        return el;
    };

    const buildUrl = (root, path) => {
        const cleanPath = path.replace(/^\//, '');
        return new URL(cleanPath, root).toString();
    };

    const initContainer = (container) => {
        const dataset = container.dataset || {};
        const examId = parseInt(dataset.examId || dataset.exam_id || '0', 10);
        const restUrl = dataset.restUrl || (window.DaneshExamConfig && window.DaneshExamConfig.restUrl) || '';
        const nonce = dataset.nonce || (window.DaneshExamConfig && window.DaneshExamConfig.nonce) || '';

        if (!examId || !restUrl) {
            container.innerHTML = '<div class="danesh-exam__error">Exam could not be loaded.</div>';
            return;
        }

        const state = {
            attemptId: null,
            questions: [],
            answers: {},
            report: null,
            remainingSeconds: null,
            timerId: null,
            isSubmitting: false,
            attemptStatus: STATUS_IN_PROGRESS,
            hasFatalError: false,
            autoSubmitInFlight: false,
        };

        const storageKey = `danesh-exam-attempt-${examId}`;

        const statusEl = createEl('div', 'danesh-exam__status');
        const timerEl = createEl('div', 'danesh-exam__timer');
        const bodyEl = createEl('div', 'danesh-exam__body');
        const actionsEl = createEl('div', 'danesh-exam__actions');
        const submitBtn = createEl('button', 'danesh-exam__submit', 'Submit Attempt');
        const reportBtn = createEl('button', 'danesh-exam__report', 'View Report');
        const resultEl = createEl('div', 'danesh-exam__result');

        submitBtn.type = 'button';
        reportBtn.type = 'button';
        reportBtn.style.display = 'none';

        actionsEl.appendChild(submitBtn);
        actionsEl.appendChild(reportBtn);

        container.innerHTML = '';
        container.appendChild(statusEl);
        container.appendChild(timerEl);
        container.appendChild(bodyEl);
        container.appendChild(actionsEl);
        container.appendChild(resultEl);

        const setStatus = (message, isError = false) => {
            statusEl.textContent = message;
            statusEl.classList.toggle('danesh-exam__status--error', Boolean(isError));
        };

        const rememberAttempt = (meta) => {
            try {
                const payload = Object.assign({}, meta || {}, {
                    attemptId: state.attemptId,
                    status: state.attemptStatus,
                });

                localStorage.setItem(storageKey, JSON.stringify(payload));
            } catch (e) {
                /* Ignore storage failures */
            }
        };

        const readStoredAttempt = () => {
            if (typeof localStorage === 'undefined') {
                return null;
            }

            return safeJsonParse(localStorage.getItem(storageKey), null);
        };

        const clearStoredAttempt = () => {
            try {
                localStorage.removeItem(storageKey);
            } catch (e) {
                /* Ignore storage failures */
            }
        };

        const renderTimer = () => {
            if (state.attemptStatus === STATUS_SUBMITTED) {
                timerEl.textContent = '';
                return;
            }

            if (state.remainingSeconds === null) {
                timerEl.textContent = '';
                return;
            }

            timerEl.textContent = `Time remaining: ${formatSeconds(state.remainingSeconds)}`;
        };

        const stopTimer = () => {
            if (state.timerId) {
                window.clearInterval(state.timerId);
                state.timerId = null;
            }
        };

        const handleTimeExpiry = async () => {
            if (!state.attemptId || state.attemptStatus === STATUS_SUBMITTED || state.autoSubmitInFlight) {
                return;
            }

            state.autoSubmitInFlight = true;
            setStatus('Time is up. Submitting your attempt...');

            await submitAttempt(true);
            state.autoSubmitInFlight = false;
        };

        const startTimer = () => {
            if (state.timerId) {
                stopTimer();
            }

            if (state.remainingSeconds === null || state.attemptStatus === STATUS_SUBMITTED) {
                renderTimer();
                return;
            }

            renderTimer();

            state.timerId = window.setInterval(() => {
                state.remainingSeconds = Math.max(0, state.remainingSeconds - 1);
                renderTimer();

                if (state.remainingSeconds <= 0 && state.timerId) {
                    stopTimer();
                    handleTimeExpiry();
                }
            }, 1000);
        };

        const apiFetch = async (path, options = {}) => {
            const url = buildUrl(restUrl, path);
            const headers = Object.assign(
                { 'Content-Type': 'application/json' },
                options.headers || {}
            );

            if (nonce) {
                headers['X-WP-Nonce'] = nonce;
            }

            const response = await fetch(url, {
                method: options.method || 'GET',
                headers,
                credentials: 'include',
                body: options.body ? JSON.stringify(options.body) : undefined,
            });

            const data = await response.json().catch(() => null);

            if (!response.ok) {
                const error = new Error((data && data.message) || 'Request failed');
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data;
        };

        const disableInputs = () => {
            container.classList.add('danesh-exam--locked');

            Array.from(container.querySelectorAll('input[type="radio"]')).forEach((input) => {
                input.disabled = true;
            });
        };

        const updateActionButtons = () => {
            const isSubmitted = state.attemptStatus === STATUS_SUBMITTED;

            submitBtn.style.display = isSubmitted ? 'none' : '';
            submitBtn.disabled = isSubmitted || state.isSubmitting || state.hasFatalError;
            reportBtn.style.display = isSubmitted ? '' : 'none';
            reportBtn.disabled = !isSubmitted;
        };

        const showReport = (report) => {
            if (!report) {
                resultEl.innerHTML = '';
                return;
            }

            resultEl.innerHTML = `
                <div class="danesh-report">
                    <div class="danesh-report__score">Score: ${report.score} / ${report.max_score}</div>
                    <div class="danesh-report__submitted">Submitted at: ${report.submitted_at || '—'}</div>
                </div>
            `;
            container.classList.add('danesh-exam--has-report');
            reportBtn.textContent = 'View Report';
        };

        const enterSubmittedState = (report = null) => {
            state.attemptStatus = STATUS_SUBMITTED;
            state.report = report || state.report;

            stopTimer();
            state.remainingSeconds = null;
            renderTimer();
            disableInputs();
            updateActionButtons();
            showReport(state.report);
            rememberAttempt({ status: STATUS_SUBMITTED });
            setStatus('Exam submitted. Report is ready.');
        };

        const renderQuestions = (questions) => {
            bodyEl.innerHTML = '';

            if (!questions || questions.length === 0) {
                bodyEl.appendChild(createEl('p', 'danesh-exam__empty', 'No questions are available for this exam.'));
                return;
            }

            questions.forEach((question, index) => {
                const wrapper = createEl('div', 'danesh-question');
                const heading = createEl('div', 'danesh-question__title', `Q${index + 1}. ${question.prompt || ''}`);
                const choicesWrap = createEl('div', 'danesh-question__choices');
                const selectedChoice = state.answers[question.id] ?? question.selected_choice_id ?? null;

                wrapper.appendChild(heading);

                (question.choices || []).forEach((choice) => {
                    const choiceId = choice.id;
                    const choiceWrapper = createEl('label', 'danesh-choice');
                    const input = document.createElement('input');

                    input.type = 'radio';
                    input.name = `danesh-question-${question.id}`;
                    input.value = String(choiceId);
                    input.checked = selectedChoice !== null && Number(selectedChoice) === Number(choiceId);
                    input.disabled = state.attemptStatus === STATUS_SUBMITTED || state.hasFatalError;

                    input.addEventListener('change', () => {
                        if (state.attemptStatus === STATUS_SUBMITTED) {
                            return;
                        }

                        state.answers[question.id] = choiceId;
                        saveAnswer(question.id, choiceId);
                    });

                    const text = createEl('span', 'danesh-choice__text', choice.text || '');

                    choiceWrapper.appendChild(input);
                    choiceWrapper.appendChild(text);
                    choicesWrap.appendChild(choiceWrapper);
                });

                wrapper.appendChild(choicesWrap);
                bodyEl.appendChild(wrapper);
            });
        };

        const loadReport = async () => {
            if (!state.attemptId) {
                return null;
            }

            try {
                setStatus('Loading report...');
                const report = await apiFetch(`attempts/${state.attemptId}/report`);
                state.report = report;
                resultEl.innerHTML = `
                    <div class="danesh-report">
                        <div class="danesh-report__score">Score: ${report.score} / ${report.max_score}</div>
                        <div class="danesh-report__submitted">Submitted at: ${report.submitted_at || '—'}</div>
                    </div>
                `;
                setStatus('Exam submitted. Report is ready.');
                return report;
            } catch (error) {
                setStatus(error.message || 'Unable to load report.', true);

                if (error.status === 401 || error.status === 403 || error.status === 404) {
                    state.hasFatalError = true;
                    disableInputs();
                    updateActionButtons();
                }

                return null;
            }
        };

        const submitAttempt = async (isAuto = false) => {
            if (!state.attemptId || state.isSubmitting || state.attemptStatus === STATUS_SUBMITTED) {
                return;
            }

            state.isSubmitting = true;
            submitBtn.disabled = true;
            setStatus(isAuto ? 'Auto-submitting attempt...' : 'Submitting attempt...');

            try {
                const report = await apiFetch(`attempts/${state.attemptId}/submit`, { method: 'POST' });
                state.report = report;
                enterSubmittedState(report);
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to submit this exam.', true);
                    state.hasFatalError = true;
                } else if (error.status === 403) {
                    setStatus('This exam is not available.', true);
                    state.hasFatalError = true;
                } else if (error.status === 404) {
                    setStatus('Attempt not found.', true);
                    state.hasFatalError = true;
                } else if (error.status === 400 && (error.data?.code === 'attempt_already_submitted')) {
                    setStatus('Attempt already submitted. Loading report...', false);
                    const report = await loadReport();
                    if (report) {
                        enterSubmittedState(report);
                    }
                } else {
                    setStatus(error.message || 'Could not submit attempt.', true);
                }
            } finally {
                state.isSubmitting = false;
                submitBtn.disabled = false;
                updateActionButtons();
            }
        };

        const saveAnswer = async (questionId, choiceId) => {
            if (!state.attemptId || state.attemptStatus === STATUS_SUBMITTED || state.hasFatalError) {
                return;
            }

            try {
                setStatus('Saving answer...');
                const response = await apiFetch(`attempts/${state.attemptId}/answers`, {
                    method: 'POST',
                    body: {
                        answers: [
                            {
                                question_id: questionId,
                                choice_id: choiceId,
                            },
                        ],
                    },
                });

                const remaining = response.remaining_seconds;

                if (typeof remaining === 'number') {
                    state.remainingSeconds = remaining;
                    startTimer();
                }

                setStatus('Answer saved.');
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to answer this exam.', true);
                    state.hasFatalError = true;
                } else if (error.status === 403) {
                    setStatus('You cannot answer this exam right now.', true);
                    state.hasFatalError = true;
                } else if (error.status === 404) {
                    setStatus('Attempt not found.', true);
                    state.hasFatalError = true;
                } else {
                    setStatus(error.message || 'Unable to save answer.', true);
                }

                if (state.hasFatalError) {
                    disableInputs();
                    updateActionButtons();
                }
            }
        };

        const loadPaper = async () => {
            if (!state.attemptId) {
                return;
            }

            try {
                setStatus('Loading questions...');
                const paper = await apiFetch(`attempts/${state.attemptId}/paper`);
                const attemptInfo = paper.attempt || {};

                state.questions = paper.questions || [];
                state.remainingSeconds = typeof attemptInfo.remaining_seconds === 'number' ? attemptInfo.remaining_seconds : null;
                state.attemptStatus = attemptInfo.status || STATUS_IN_PROGRESS;

                renderQuestions(state.questions);
                startTimer();
                setStatus(attemptInfo.status === 'in_progress' ? 'Attempt in progress' : 'Ready');
                updateActionButtons();
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to view this exam.', true);
                    state.hasFatalError = true;
                    disableInputs();
                    updateActionButtons();
                } else if (error.status === 403) {
                    setStatus('This exam is not available.', true);
                    state.hasFatalError = true;
                    disableInputs();
                    updateActionButtons();
                } else if (error.status === 404) {
                    setStatus('Attempt not found.', true);
                    state.hasFatalError = true;
                    disableInputs();
                    updateActionButtons();
                } else if (error.status === 400 && (error.data?.code === 'attempt_already_submitted')) {
                    setStatus('Attempt already submitted. Loading report...', false);
                    const report = await loadReport();
                    if (report) {
                        enterSubmittedState(report);
                    }
                } else {
                    setStatus(error.message || 'Unable to load exam questions.', true);
                }
            }
        };

        const startAttempt = async () => {
            try {
                setStatus('Starting your attempt...');
                const response = await apiFetch(`exams/${examId}/attempts`, { method: 'POST' });

                state.attemptId = response.id || response.attempt_id || null;
                state.remainingSeconds = typeof response.remaining_seconds === 'number' ? response.remaining_seconds : null;
                state.attemptStatus = response.status || STATUS_IN_PROGRESS;

                if (!state.attemptId) {
                    setStatus('Unable to start attempt.', true);
                    return;
                }

                rememberAttempt({ status: state.attemptStatus });
                startTimer();
                await loadPaper();
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to take this exam.', true);
                    state.hasFatalError = true;
                } else if (error.status === 403) {
                    setStatus('The exam is not available.', true);
                    state.hasFatalError = true;
                } else if (error.status === 404) {
                    setStatus('Exam not found.', true);
                    state.hasFatalError = true;
                } else {
                    setStatus(error.message || 'Unable to start exam.', true);
                }

                updateActionButtons();
            }
        };

        const loadActiveAttempt = async () => {
            try {
                setStatus('Checking for an active attempt...');
                const activeAttempt = await apiFetch(`exams/${examId}/attempts/active`);

                state.attemptId = activeAttempt.id || activeAttempt.attempt_id || null;
                state.remainingSeconds = typeof activeAttempt.remaining_seconds === 'number' ? activeAttempt.remaining_seconds : null;
                state.attemptStatus = activeAttempt.status || STATUS_IN_PROGRESS;
                rememberAttempt({ status: state.attemptStatus });

                if (!state.attemptId) {
                    setStatus('Unable to load active attempt.', true);
                    state.hasFatalError = true;
                    updateActionButtons();
                    return;
                }

                await loadPaper();
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to take this exam.', true);
                    state.hasFatalError = true;
                    updateActionButtons();
                    return;
                }

                if (error.status === 403) {
                    setStatus('You cannot access this exam.', true);
                    state.hasFatalError = true;
                    updateActionButtons();
                    return;
                }

                if (error.status === 404) {
                    const stored = readStoredAttempt();

                    if (stored && stored.status === STATUS_SUBMITTED && stored.attemptId) {
                        state.attemptId = stored.attemptId;
                        setStatus('Loading submitted report...');
                        const report = await loadReport();
                        if (report) {
                            enterSubmittedState(report);
                        }
                        updateActionButtons();
                        return;
                    }

                    await startAttempt();
                    return;
                }

                setStatus(error.message || 'Unable to load active attempt.', true);
                state.hasFatalError = true;
                updateActionButtons();
            }
        };

        submitBtn.addEventListener('click', submitAttempt);
        reportBtn.addEventListener('click', async () => {
            if (!state.attemptId) {
                return;
            }

            const report = await loadReport();
            if (report) {
                enterSubmittedState(report);
            }
        });

        loadActiveAttempt();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll(SELECTOR);
        containers.forEach((container) => initContainer(container));
    });
})();
