// Client-side behaviour in plain JavaScript (no libraries).

// 1. Live ticket filtering: search box + status dropdown on the ticket list
const search = document.getElementById('ticket-search');
const statusFilter = document.getElementById('status-filter');
const ticketRows = document.querySelectorAll('#ticket-table tbody tr');
const noMatches = document.getElementById('no-matches');

function filterTickets() {
    const text = search.value.trim().toLowerCase();
    const status = statusFilter.value;
    let visible = 0;

    ticketRows.forEach(row => {
        const matchesText = row.textContent.toLowerCase().includes(text);
        const matchesStatus = status === '' || row.dataset.status === status;
        row.hidden = !(matchesText && matchesStatus);
        if (!row.hidden) visible++;
    });
    noMatches.hidden = visible > 0;
}

if (search && statusFilter) {
    search.addEventListener('input', filterTickets);
    statusFilter.addEventListener('change', filterTickets);
}

// 2. Live filtering on the audit log
const auditSearch = document.getElementById('audit-search');
if (auditSearch) {
    const auditRows = document.querySelectorAll('#audit-table tbody tr');
    auditSearch.addEventListener('input', () => {
        const text = auditSearch.value.trim().toLowerCase();
        auditRows.forEach(row => {
            row.hidden = !row.textContent.toLowerCase().includes(text);
        });
    });
}

// 3. Client-side validation: stop empty or whitespace-only submissions.
//    The server validates again, because browser checks can be bypassed.
document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', event => {
        let firstInvalid = null;
        form.querySelectorAll('[required]').forEach(field => {
            const empty = field.value.trim() === '';
            field.classList.toggle('invalid', empty);
            if (empty && !firstInvalid) firstInvalid = field;
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
        }
    });
});
