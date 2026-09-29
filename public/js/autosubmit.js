// Filters apply as soon as a choice is made; the Show button stays for searching.
document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
    select.addEventListener('change', () => select.form.submit());
});
