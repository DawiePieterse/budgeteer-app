// Overview charts: a readout of the numbers under the pointer or keyboard focus, and a line at the day
// on the pace chart. Everything it shows is also in each mark's label and in the tables, so the page
// works without it. Names come from the household's own groups, so they are set as text, never HTML.
const tip = document.querySelector('.ov-tip');
let shown = null;

function fill(mark) {
    const title = document.createElement('p');
    title.className = 'ov-tip-title';
    title.textContent = mark.dataset.tipTitle ?? '';
    const rows = JSON.parse(mark.dataset.tip || '[]').map(([label, value, key]) => {
        const row = document.createElement('p');
        const swatch = document.createElement('span');
        swatch.className = `ov-tip-key ${key ?? ''}`;
        const strong = document.createElement('strong');
        strong.textContent = value;
        const name = document.createElement('span');
        name.textContent = label;
        row.append(swatch, strong, name);
        return row;
    });
    tip.replaceChildren(title, ...rows);
    // A group's colour follows its slice into the readout.
    tip.className = `ov-tip ${[...mark.classList].find((c) => c.startsWith('group-colour-')) ?? ''}`;
}

function place(x, y) {
    const gap = 14;
    const { width, height } = tip.getBoundingClientRect();
    const left = x + gap + width > window.innerWidth - 8 ? x - gap - width : x + gap;
    const top = Math.max(8, Math.min(y - height - gap, window.innerHeight - height - 8));
    tip.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
}

function crosshair(mark, on) {
    const line = mark.closest('svg')?.querySelector('.chart-crosshair');
    if (line && mark.dataset.x) {
        line.setAttribute('x1', mark.dataset.x);
        line.setAttribute('x2', mark.dataset.x);
        line.classList.toggle('is-on', on);
    }
}

function show(mark, x, y) {
    if (shown !== mark) {
        if (shown) {
            hide();
        }
        fill(mark);
        tip.hidden = false;
        mark.classList.add('is-hover');
        crosshair(mark, true);
        shown = mark;
    }
    place(x, y);
}

function hide() {
    if (shown) {
        shown.classList.remove('is-hover');
        crosshair(shown, false);
    }
    tip.hidden = true;
    shown = null;
}

if (tip) {
    document.addEventListener('pointermove', (event) => {
        const mark = event.target.closest?.('[data-tip]');
        mark ? show(mark, event.clientX, event.clientY) : hide();
    });
    document.addEventListener('pointerleave', hide);
    document.addEventListener('focusin', (event) => {
        const mark = event.target.closest?.('[data-tip]');
        if (mark) {
            const box = mark.getBoundingClientRect();
            show(mark, box.left + box.width / 2, box.top + 12);
        } else {
            hide();
        }
    });
    document.addEventListener('focusout', hide);
    document.addEventListener('scroll', hide, { passive: true });

    // The pace chart is one stop for the Tab key; the arrow keys then step from day to day.
    document.querySelectorAll('.chart-pace').forEach((chart) => {
        chart.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }
            const days = [...chart.querySelectorAll('.chart-hit')];
            const next = days[days.indexOf(document.activeElement) + (event.key === 'ArrowRight' ? 1 : -1)];
            if (next) {
                event.preventDefault();
                days.forEach((d) => d.setAttribute('tabindex', '-1'));
                next.setAttribute('tabindex', '0');
                next.focus();
            }
        });
    });
}
