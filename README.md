# Daneshdfp 1

## Student exam UI behavior
- The student exam view now resumes active attempts on load (and restores the last submitted attempt if available), pre-selecting any saved answers and honoring server-provided timing.
- After a successful submission, the UI locks: inputs are disabled, the timer is hidden, the submit button is removed, and a report button/section is shown using the server report endpoint response.
- The client timer is driven by remaining time from the server and will auto-submit when it reaches zero, gracefully handling already-submitted attempts.
