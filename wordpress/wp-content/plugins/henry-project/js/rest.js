/**
 * REST API Implementation
 */
document.addEventListener('DOMContentLoaded', function() {
    const state = {
        page: 1,
        order: 'DESC',
        loading: false,
        perPage: henryProject.perPage || 10
    };

    const entriesContainer = document.getElementById('entries-list');
    const paginationContainer = document.getElementById('pagination');
    const form = document.getElementById('entry-form');
    const sortToggle = document.getElementById('sort-toggle');

    async function fetchEntries() {
        if (state.loading) return;
        state.loading = true;

        entriesContainer.classList.add('loading');

        try {
            const response = await wp.apiFetch({
                path: `henry-project/v1/entries?page=${state.page}&order=${state.order}&per_page=${state.perPage}`
            });

            renderEntries(response.entries);
            renderPagination(response.total_pages);
        } catch (error) {
            showError(error.message || 'Error loading entries');
        } finally {
            state.loading = false;
            entriesContainer.classList.remove('loading');
        }
    }

    function renderEntries(entries) {
        if (!entries.length) {
            entriesContainer.innerHTML = `
                <div class="alert alert-info">
                    No entries found.
                </div>
            `;
            return;
        }

        entriesContainer.innerHTML = entries.map(entry => `
            <div class="henry-project-entry" data-entry-id="${entry.id}">
                <div class="d-flex justify-content-between">
                    <div class="entry-content ${entry.can_edit ? 'editable' : ''}"
                         ${entry.can_edit ? 'contenteditable="true"' : ''}>
                        ${escapeHtml(entry.content)}
                    </div>
                    ${entry.can_edit ? `
                        <div class="henry-project-actions">
                            <button class="btn btn-sm btn-outline-danger delete-entry">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    ` : ''}
                </div>
                <small class="text-muted d-block mt-2">
                    By ${escapeHtml(entry.author.name)}
                    (${entry.author.roles.join(', ')})
                    on ${new Date(entry.date).toLocaleDateString()}
                </small>
            </div>
        `).join('');

        initializeEntryHandlers();
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        const pages = [];
        for (let i = 1; i <= totalPages; i++) {
            pages.push(`
                <li class="page-item ${i === state.page ? 'active' : ''}">
                    <button class="page-link" data-page="${i}">${i}</button>
                </li>
            `);
        }

        paginationContainer.innerHTML = `
            <nav aria-label="Entries pagination">
                <ul class="pagination">
                    ${pages.join('')}
                </ul>
            </nav>
        `;

        initializePaginationHandlers();
    }

    function initializeEntryHandlers() {
        document.querySelectorAll('.entry-content.editable').forEach(elem => {
            elem.addEventListener('blur', handleEdit);
            elem.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    elem.blur();
                }
            });
        });

        document.querySelectorAll('.delete-entry').forEach(btn => {
            btn.addEventListener('click', handleDelete);
        });
    }

    function initializePaginationHandlers() {
        paginationContainer.querySelectorAll('.page-link').forEach(btn => {
            btn.addEventListener('click', e => {
                state.page = parseInt(e.target.dataset.page);
                fetchEntries();
            });
        });
    }

    async function handleSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('entry-content');
        const content = input.value.trim();

        if (!content) return;

        try {
            await wp.apiFetch({
                path: 'henry-project/v1/entries',
                method: 'POST',
                data: { content }
            });

            input.value = '';
            state.page = 1;
            fetchEntries();
            showSuccess('Entry added successfully');
        } catch (error) {
            showError(error.message || 'Error adding entry');
        }
    }

    async function handleEdit(e) {
        const content = e.target.innerText.trim();
        const entryId = e.target.closest('[data-entry-id]').dataset.entryId;

        if (!content) {
            fetchEntries();
            return;
        }

        try {
            await wp.apiFetch({
                path: `henry-project/v1/entries/${entryId}`,
                method: 'PUT',
                data: { content }
            });
            showSuccess('Entry updated successfully');
        } catch (error) {
            showError(error.message || 'Error updating entry');
            fetchEntries();
        }
    }

    async function handleDelete(e) {
        if (!confirm('Are you sure you want to delete this entry?')) return;

        const entryElement = e.target.closest('[data-entry-id]');
        const entryId = entryElement.dataset.entryId;

        entryElement.classList.add('deleting');

        try {
            await wp.apiFetch({
                path: `henry-project/v1/entries/${entryId}`,
                method: 'DELETE'
            });
            fetchEntries();
            showSuccess('Entry deleted successfully');
        } catch (error) {
            showError(error.message || 'Error deleting entry');
            entryElement.classList.remove('deleting');
        }
    }

    function showError(message) {
        const alert = createAlert(message, 'danger');
        entriesContainer.insertAdjacentElement('beforebegin', alert);
    }

    function showSuccess(message) {
        const alert = createAlert(message, 'success');
        entriesContainer.insertAdjacentElement('beforebegin', alert);
    }

    function createAlert(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        setTimeout(() => {
            alert.remove();
        }, 3000);

        return alert;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Event listeners
    form.addEventListener('submit', handleSubmit);

    sortToggle.addEventListener('click', () => {
        state.order = state.order === 'DESC' ? 'ASC' : 'DESC';
        sortToggle.querySelector('i').classList.toggle('bi-sort-down');
        sortToggle.querySelector('i').classList.toggle('bi-sort-up');
        fetchEntries();
    });

    // Initial load
    fetchEntries();
});