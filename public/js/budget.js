// Budget screen: the Save bar shows once something is changed, and a chosen icon shows straight away.
// Without JavaScript the Save bar is always there and the icon changes once saved.
document.querySelectorAll('form[data-save-bar]').forEach((form) => {
    const bar = form.querySelector('.save-bar');
    if (!bar) {
        return;
    }
    bar.hidden = true;
    const show = () => {
        bar.hidden = false;
    };
    form.addEventListener('input', show);
    form.addEventListener('change', show);
    form.addEventListener('reset', () => {
        bar.hidden = true;
        // After the form has put the old values back, show the old icons again.
        setTimeout(() => form.querySelectorAll('select[data-icon-pick]').forEach(drawIcon));
    });
});

function drawIcon(select) {
    const path = select.closest('.icon-pick')?.querySelector('svg path');
    const chosen = select.selectedOptions[0];
    if (path && chosen?.dataset.path) {
        path.setAttribute('d', chosen.dataset.path);
    }
}

document.querySelectorAll('select[data-icon-pick]').forEach((select) => {
    select.addEventListener('change', () => drawIcon(select));
});
