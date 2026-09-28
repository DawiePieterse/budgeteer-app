// Turns a PDF (opened with pdf.js) into lines of positioned text, so the server
// can read a bank statement without ever receiving the PDF itself.
//
// Result: { pages: [ [ { y, items: [ { x, s } ] } ] ] }, top to bottom, left to right.

const SAME_LINE = 2.5; // points: items this close vertically are on one line

export async function statementText(pdf) {
    const pages = [];
    for (let n = 1; n <= pdf.numPages; n++) {
        const page = await pdf.getPage(n);
        const content = await page.getTextContent();
        const rows = [];
        for (const item of content.items) {
            const s = item.str.replace(/ /g, ' ');
            if (s.trim() === '') continue;
            const x = Math.round(item.transform[4] * 10) / 10;
            const y = Math.round(item.transform[5] * 10) / 10;
            let row = rows.find((r) => Math.abs(r.y - y) <= SAME_LINE);
            if (!row) {
                row = { y, items: [] };
                rows.push(row);
            }
            row.items.push({ x, s: s.trim() });
        }
        rows.sort((a, b) => b.y - a.y);
        for (const row of rows) row.items.sort((a, b) => a.x - b.x);
        pages.push(rows);
    }
    return { pages };
}
