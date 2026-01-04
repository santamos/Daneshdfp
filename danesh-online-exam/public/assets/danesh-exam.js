(() => {
    const SELECTOR = '.danesh-exam';

    const formatSeconds = (value) => {
        if (value === null || typeof value === 'undefined') {
            return '—';
        }

        const seconds = Math.max(0, Math.floor(value));
        const minutes = Math.floor(seconds / 60);
        const remaining = seconds % 60;

        return `${minutes}:${remaining.toString().padStart(2, '0')}`;
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
        };

        const statusEl = createEl('div', 'danesh-exam__status');
        const timerEl = createEl('div', 'danesh-exam__timer');
        const bodyEl = createEl('div', 'danesh-exam__body');
        const actionsEl = createEl('div', 'danesh-exam__actions');
        const submitBtn = createEl('button', 'danesh-exam__submit', 'Submit Attempt');
        const resultEl = createEl('div', 'danesh-exam__result');

        submitBtn.type = 'button';

        actionsEl.appendChild(submitBtn);

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

        const renderTimer = () => {
            if (state.remainingSeconds === null) {
                timerEl.textContent = '';
                return;
            }

            timerEl.textContent = `Time remaining: ${formatSeconds(state.remainingSeconds)}`;
        };

        const startTimer = () => {
            if (state.timerId) {
                window.clearInterval(state.timerId);
            }

            if (state.remainingSeconds === null) {
                renderTimer();
                return;
            }

            renderTimer();

            state.timerId = window.setInterval(() => {
                state.remainingSeconds = Math.max(0, state.remainingSeconds - 1);
                renderTimer();

                if (state.remainingSeconds <= 0 && state.timerId) {
                    window.clearInterval(state.timerId);
                    state.timerId = null;
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

                    input.addEventListener('change', () => {
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
                return;
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
            } catch (error) {
                setStatus(error.message || 'Unable to load report.', true);
            }
        };

        const submitAttempt = async () => {
            if (!state.attemptId || state.isSubmitting) {
                return;
            }

            state.isSubmitting = true;
            submitBtn.disabled = true;
            setStatus('Submitting attempt...');

            try {
                await apiFetch(`attempts/${state.attemptId}/submit`, { method: 'POST' });
                await loadReport();
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to submit this exam.', true);
                } else if (error.status === 403) {
                    setStatus('This exam is not available.', true);
                } else {
                    setStatus(error.message || 'Could not submit attempt.', true);
                }
            } finally {
                state.isSubmitting = false;
                submitBtn.disabled = false;
            }
        };

        const saveAnswer = async (questionId, choiceId) => {
            if (!state.attemptId) {
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
                } else if (error.status === 403) {
                    setStatus('You cannot answer this exam right now.', true);
                } else {
                    setStatus(error.message || 'Unable to save answer.', true);
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

                renderQuestions(state.questions);
                startTimer();
                setStatus(attemptInfo.status === 'in_progress' ? 'Attempt in progress' : 'Ready');
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to view this exam.', true);
                } else if (error.status === 403) {
                    setStatus('This exam is not available.', true);
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

                if (!state.attemptId) {
                    setStatus('Unable to start attempt.', true);
                    return;
                }

                startTimer();
                await loadPaper();
            } catch (error) {
                if (error.status === 401) {
                    setStatus('Please log in to take this exam.', true);
                } else if (error.status === 403) {
                    setStatus('The exam is not available.', true);
                } else {
                    setStatus(error.message || 'Unable to start exam.', true);
                }
            }
        };

        submitBtn.addEventListener('click', submitAttempt);

        startAttempt();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const containers = document.querySelectorAll(SELECTOR);
        containers.forEach((container) => initContainer(container));
    });
})();
