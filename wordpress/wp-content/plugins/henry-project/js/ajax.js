/**
 * AJAX Implementation
 */
jQuery(function($) {
    const default_per_page = 10;
    const state = {
        page: 1,
        order: 'DESC',
        loading: false,
        perPage: henryProject.perPage || default_per_page
    };

    const entriesContainer = $('#entries-list');
    const paginationContainer = $('#pagination');
    const form = $('#entry-form');
    const sortToggle = $('#sort-toggle');

    function fetchEntries() {
        if (state.loading) return;
        state.loading = true;

        entriesContainer.addClass('loading');

        $.ajax({
            url: henryProject.ajaxUrl,
            type: 'GET',
            data: {
                action: 'henry_project_get_entries',
                nonce: henryProject.nonce,
                page: state.page,
                order: state.order
            },
            success: function(response) {
                if (response.success) {
                    renderEntries(response.data.entries);
                    renderPagination(response.data.total_pages);
                } else {
                    showError(response.data.message || 'Error loading entries');
                }
            },
            error: function(xhr, status, error) {
                showError('Error loading entries: ' + error);
            },
            complete: function() {
                state.loading = false;
                entriesContainer.removeClass('loading');
            }
        });
    }

    function renderEntries(entries) {
        if (!entries.length) {
            entriesContainer.html(`
                <div class="alert alert-info">
                    No entries available for your role level.
                </div>
            `);
            return;
        }

        entriesContainer.html(entries.map(entry => `
            <div class="henry-project-entry" data-entry-id="${entry.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="entry-content ${entry.can_edit ? 'editable' : ''}"
                         ${entry.can_edit ? 'contenteditable="true"' : ''}>
                        ${escapeHtml(entry.content)}
                    </div>
                    ${entry.can_edit ? `
                        <div class="henry-project-actions ms-3">
                            <button class="btn btn-sm btn-outline-danger delete-entry"
                                    title="Delete Entry">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    ` : ''}
                </div>
                <div class="entry-meta d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">
                        By ${escapeHtml(entry.author.name)}
                        <span class="badge bg-secondary ms-1">${entry.author.roles.join(', ')}</span>
                    </small>
                    <small class="text-muted">
                        ${new Date(entry.date).toLocaleDateString()}
                    </small>
                </div>
            </div>
        `).join(''));

        initializeEntryHandlers();
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            paginationContainer.empty();
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

        paginationContainer.html(`
            <nav aria-label="Entries pagination">
                <ul class="pagination justify-content-center">
                    ${pages.join('')}
                </ul>
            </nav>
        `);

        initializePaginationHandlers();
    }

    function initializeEntryHandlers() {
        $('.entry-content.editable')
            .on('blur', handleEdit)
            .on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });

        $('.delete-entry').on('click', handleDelete);
    }

    function initializePaginationHandlers() {
        paginationContainer.find('.page-link').on('click', function() {
            state.page = parseInt($(this).data('page'));
            fetchEntries();
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        const input = $('#entry-content');
        const content = input.val().trim();
        const submitBtn = form.find('[type="submit"]');

        if (!content) return;

        submitBtn.prop('disabled', true);

        $.ajax({
            url: henryProject.ajaxUrl,
            type: 'POST',
            data: {
                action: 'henry_project_create_entry',
                nonce: henryProject.nonce,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    input.val('');
                    state.page = 1;
                    fetchEntries();
                    showSuccess('Entry added successfully');
                } else {
                    showError(response.data.message || 'Error adding entry');
                }
            },
            error: function(xhr, status, error) {
                showError('Error adding entry: ' + error);
            },
            complete: function() {
                submitBtn.prop('disabled', false);
            }
        });
    }

    function handleEdit(e) {
        const $target = $(e.target);
        const content = $target.text().trim();
        const entryId = $target.closest('[data-entry-id]').data('entryId');

        if (!content) {
            fetchEntries();
            return;
        }

        $.ajax({
            url: henryProject.ajaxUrl,
            type: 'POST',
            data: {
                action: 'henry_project_update_entry',
                nonce: henryProject.nonce,
                id: entryId,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Entry updated successfully');
                } else {
                    showError(response.data.message || 'Error updating entry');
                    fetchEntries();
                }
            },
            error: function(xhr, status, error) {
                showError('Error updating entry: ' + error);
                fetchEntries();
            }
        });
    }

    function handleDelete(e) {
        if (!confirm('Are you sure you want to delete this entry?')) return;

        const $entry = $(e.target).closest('[data-entry-id]');
        const entryId = $entry.data('entryId');

        $entry.addClass('deleting');

        $.ajax({
            url: henryProject.ajaxUrl,
            type: 'POST',
            data: {
                action: 'henry_project_delete_entry',
                nonce: henryProject.nonce,
                id: entryId
            },
            success: function(response) {
                if (response.success) {
                    fetchEntries();
                    showSuccess('Entry deleted successfully');
                } else {
                    showError(response.data.message || 'Error deleting entry');
                    $entry.removeClass('deleting');
                }
            },
            error: function(xhr, status, error) {
                showError('Error deleting entry: ' + error);
                $entry.removeClass('deleting');
            }
        });
    }

    function showError(message) {
        showAlert(message, 'danger');
    }

    function showSuccess(message) {
        showAlert(message, 'success');
    }

    function showAlert(message, type) {
        $('.alert').remove();

        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close">×</button>
            </div>
        `);

        entriesContainer.before(alert);

        setTimeout(() => {
            if (alert.length) {
                alert.remove();
            }
        }, 3000);
    }

    function escapeHtml(str) {
        return $('<div>').text(str).html();
    }

    // Event listeners
    form.on('submit', handleSubmit);

    sortToggle.on('click', function() {
        state.order = state.order === 'DESC' ? 'ASC' : 'DESC';
        $(this).find('i').toggleClass('bi-sort-down bi-sort-up');
        $(this).attr('title', `Sort ${state.order === 'DESC' ? 'Oldest First' : 'Newest First'}`);
        fetchEntries();
    });

    // Initialize tooltip
    sortToggle.attr('title', 'Sort Newest First');

    // Initial load
    fetchEntries();
});