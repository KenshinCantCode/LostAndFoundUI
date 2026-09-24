
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.querySelector('[data-nav-toggle]');
    const mobileNav = document.querySelector('[data-nav-mobile]');
    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', function() {
            mobileNav.classList.toggle('hidden');
        });
    }

    document.querySelectorAll('[data-dismiss="alert"]').forEach(function(button) {
        button.addEventListener('click', function() {
            button.closest('.alert').remove();
        });
    });

    document.querySelectorAll('[data-ui-toggle="dropdown"]').forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            const menu = toggle.parentElement.querySelector('.dropdown-menu');
            if (menu) menu.classList.toggle('show');
        });
    });

    document.querySelectorAll('[data-ui-toggle="modal"]').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const modal = document.querySelector(toggle.getAttribute('data-ui-target'));
            if (modal) modal.classList.add('show');
        });
    });

    document.querySelectorAll('[data-ui-dismiss="modal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            button.closest('.modal').classList.remove('show');
        });
    });

    document.querySelectorAll('[data-ui-toggle="tab"]').forEach(function(tab) {
        tab.addEventListener('click', function(event) {
            event.preventDefault();
            document.querySelectorAll('[data-ui-toggle="tab"]').forEach(function(item) { item.classList.remove('active'); });
            document.querySelectorAll('.tab-pane').forEach(function(pane) { pane.classList.remove('show', 'active'); });
            tab.classList.add('active');
            const pane = document.querySelector(tab.getAttribute('href'));
            if (pane) pane.classList.add('show', 'active');
        });
    });
});

document.addEventListener('click', function(event) {
    const toggle = event.target.closest('[data-menu-toggle]');
    const openMenus = document.querySelectorAll('[data-menu-toggle][aria-expanded="true"]');

    if (toggle) {
        event.preventDefault();
        const menu = document.getElementById(toggle.getAttribute('data-menu-toggle'));
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        document.querySelectorAll('[data-menu-toggle]').forEach(function(item) { item.setAttribute('aria-expanded', 'false'); });
        document.querySelectorAll('[id="user-menu"]').forEach(function(item) { item.classList.add('hidden'); });
        if (menu && !isOpen) {
            menu.classList.remove('hidden');
            toggle.setAttribute('aria-expanded', 'true');
        }
        return;
    }

    if (openMenus.length && !event.target.closest('#user-menu')) {
        openMenus.forEach(function(item) { item.setAttribute('aria-expanded', 'false'); });
        document.querySelectorAll('[id="user-menu"]').forEach(function(item) { item.classList.add('hidden'); });
    }
});

const SITE_URL = document.body.dataset.siteUrl || window.location.origin;

if (typeof $ !== 'undefined') {
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

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

        $('form[data-confirm]').submit(function(e) {
            const message = $(this).data('confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });

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
}

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


function copyTextToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
    }, function() {
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
    const toast = document.createElement('div');
    toast.className = 'site-toast ' + (type === 'success' ? 'success' : 'danger');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3500);
}

// Initial load - fetch unread notification count (if logged in)
if (typeof $ !== 'undefined') {
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
}
