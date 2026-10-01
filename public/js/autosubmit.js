// Filters apply as soon as a choice is made, so their Show button is only needed without JavaScript.
document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
    select.addEventListener('change', () => select.form.submit());
});
document.querySelectorAll('[data-autosubmit-hide]').forEach((button) => {
    button.hidden = true;
});
