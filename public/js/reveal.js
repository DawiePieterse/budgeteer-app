// Shows a field only when a choice is made, for example the name box for "someone new". The choice is a
// select, or a group of radio buttons around them. Without JavaScript the field simply stays visible.
document.querySelectorAll('[data-reveal]').forEach((control) => {
    const target = document.getElementById(control.dataset.revealTarget);
    if (!target) {
        return;
    }
    const input = target.querySelector('input');
    const value = () => (control.matches('select') ? control.value : control.querySelector('input[type=radio]:checked')?.value ?? '');
    const update = () => {
        const chosen = value() === control.dataset.reveal;
        target.hidden = !chosen;
        if (input) {
            input.required = chosen;
        }
    };
    control.addEventListener('change', (event) => {
        // Only the choice itself, not typing in the field it shows.
        if (event.target !== control && event.target.type !== 'radio') {
            return;
        }
        update();
        if (!target.hidden && input) {
            input.focus();
        }
    });
    update();
});
