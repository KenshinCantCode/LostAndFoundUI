
document.addEventListener('DOMContentLoaded', function() {
    const themeToggles = document.querySelectorAll('[data-theme-toggle]');
    if (themeToggles.length) {
        const updateThemeToggle = function() {
            const isDark = document.documentElement.classList.contains('dark-mode');
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
            themeToggles.forEach(function(themeToggle) {
                themeToggle.setAttribute('aria-label', label);
                themeToggle.setAttribute('title', label);
                themeToggle.setAttribute('aria-pressed', String(isDark));
                themeToggle.innerHTML = '<i class="fas fa-' + (isDark ? 'sun' : 'moon') + '" aria-hidden="true"></i>';
            });
        };

        updateThemeToggle();
        themeToggles.forEach(function(themeToggle) {
            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark-mode');
                try {
                    localStorage.setItem('ui-theme', isDark ? 'dark' : 'light');
                } catch (error) {
                    // The current-page theme still works when storage is unavailable.
                }
                updateThemeToggle();
            });
        });
    }

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
            if (!menu) return;

            if (menu.classList.contains('show')) {
                closeDropdown(menu);
                return;
            }

            document.querySelectorAll('.dropdown-menu.show').forEach(closeDropdown);
            menu._dropdownToggle = toggle;
            document.body.appendChild(menu);
            menu.classList.add('show', 'dropdown-menu-floating');
            toggle.setAttribute('aria-expanded', 'true');
            positionDropdown(toggle, menu);
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

function closeDropdown(menu) {
    const toggle = menu._dropdownToggle;
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.parentElement.appendChild(menu);
        delete menu._dropdownToggle;
    }
    menu.classList.remove('show', 'dropdown-menu-floating');
    menu.style.left = '';
    menu.style.top = '';
    menu.style.maxHeight = '';
}

function positionDropdown(toggle, menu) {
    const toggleRect = toggle.getBoundingClientRect();
    const margin = 8;
    const availableBelow = window.innerHeight - toggleRect.bottom - margin * 2;
    const availableAbove = toggleRect.top - margin * 2;
    const openAbove = availableBelow < Math.min(menu.scrollHeight, 240) && availableAbove > availableBelow;
    const availableHeight = Math.max(80, openAbove ? availableAbove : availableBelow);

    menu.style.maxHeight = availableHeight + 'px';
    menu.style.left = Math.max(margin, Math.min(toggleRect.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - margin)) + 'px';
    menu.style.top = openAbove
        ? Math.max(margin, toggleRect.top - Math.min(menu.scrollHeight, availableHeight) - 4) + 'px'
        : Math.min(toggleRect.bottom + 4, window.innerHeight - margin) + 'px';
}

document.addEventListener('click', function(event) {
    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
        if (!menu.contains(event.target) && menu._dropdownToggle !== event.target.closest('[data-ui-toggle="dropdown"]')) {
            closeDropdown(menu);
        }
    });

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

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.dropdown-menu.show').forEach(closeDropdown);
    }
});

window.addEventListener('resize', function() {
    document.querySelectorAll('.dropdown-menu-floating.show').forEach(function(menu) {
        if (menu._dropdownToggle) positionDropdown(menu._dropdownToggle, menu);
    });
});

window.addEventListener('scroll', function() {
    document.querySelectorAll('.dropdown-menu-floating.show').forEach(function(menu) {
        if (menu._dropdownToggle) positionDropdown(menu._dropdownToggle, menu);
    });
}, true);

const SITE_URL = document.body.dataset.siteUrl || window.location.origin;

if (typeof $ !== 'undefined') {
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        $('input[type="file"][accept*="image"]').change(function() {
            const input = this;
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                const previewId = $(input).attr('id') + 'Preview';

                reader.onload = function(e) {
                    $('#' + previewId).attr('src', e.target.result);
                    $('#' + previewId).show();
                    if (input.id === 'avatar') {
                        $('.profile-avatar-current').hide();
                    }
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
