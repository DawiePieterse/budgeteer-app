// Shows a field only when a select has a given choice, for example the name box for "someone new".
// Without JavaScript the field simply stays visible.
document.querySelectorAll('select[data-reveal]').forEach((select) => {
    const target = document.getElementById(select.dataset.revealTarget);
    if (!target) {
        return;
    }
    const input = target.querySelector('input');
    const update = () => {
        const chosen = select.value === select.dataset.reveal;
        target.hidden = !chosen;
        if (input) {
            input.required = chosen;
        }
    };
    select.addEventListener('change', () => {
        update();
        if (!target.hidden && input) {
            input.focus();
        }
    });
    update();
});
