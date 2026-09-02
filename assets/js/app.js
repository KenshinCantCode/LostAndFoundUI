// Global application JavaScript
$(document).ready(function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Image preview on file selection
    $('input[type="file"][accept*="image"]').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            const previewId = $(this).attr('id') + 'Preview';
            
            reader.onload = function(e) {
                $('#' + previewId).attr('src', e.target.result);
                $('#' + previewId).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Confirm delete actions
    $('form[data-confirm]').submit(function(e) {
        const message = $(this).data('confirm') || 'Are you sure?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });

    // Live search suggestions
    $('#liveSearch').on('keyup', debounce(function() {
        const query = $(this).val();
        if (query.length < 2) {
            $('#searchResults').html('').hide();
            return;
        }

        $.ajax({
            url: SITE_URL + '/api/search.php',
            method: 'GET',
            data: { q: query, limit: 5 },
            success: function(response) {
                if (response.success && response.results) {
                    let html = '';
                    response.results.forEach(function(item) {
                        html += '<a href="item.php?id=' + item.id + '" class="list-group-item list-group-item-action">';
                        html += '<div class="d-flex justify-content-between">';
                        html += '<span>' + item.title + '</span>';
                        html += '<span class="badge ' + (item.type === 'lost' ? 'bg-danger' : 'bg-success') + '">' + item.type + '</span>';
                        html += '</div></a>';
                    });
                    $('#searchResults').html(html).show();
                }
            }
        });
    }, 300));
});

// Utility function for debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        const later = function() {
            timeout = null;
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Copy to clipboard
function copyTextToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // showToast('Copied to clipboard!', 'success');
    }, function() {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    });
}

// Simple toast notification (optional enhancement)
function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
            <div class="toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>`;
    $('body').append(toastHtml);
    $('.toast').toast({ delay: 3000 });
    $('.toast').toast('show');
    setTimeout(function() { $('.toast').remove(); }, 3500);
}

// Initial load - fetch unread notification count (if logged in)
$(document).ready(function() {
    if ($('#notif-count').length) {
        $.ajax({
            url: SITE_URL + '/api/notifications.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    $('#notif-count').text(response.unread_count > 0 ? response.unread_count : '');
                    if (response.unread_count > 0) {
                        $('#notif-count').show();
                    } else {
                        $('#notif-count').hide();
                    }
                }
            }
        });
    }
});

// Global site URL for JS
const SITE_URL = window.location.origin + '/campus-lost-found';
