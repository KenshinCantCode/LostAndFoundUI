document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('#people-search');
    const list = document.querySelector('#people-suggestions');
    const status = document.querySelector('#people-search-status');
    if (!input || !list || !status) return;

    let timer;
    let controller;
    let activeIndex = -1;

    const closeSuggestions = function() {
        list.classList.add('hidden');
        list.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const showMessage = function(message) {
        activeIndex = -1;
        list.replaceChildren();
        const item = document.createElement('div');
        item.className = 'px-4 py-3 text-sm text-slate-500';
        item.textContent = message;
        list.appendChild(item);
        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
        status.textContent = message;
    };

    const renderResults = function(results) {
        activeIndex = -1;
        list.replaceChildren();
        if (!results.length) {
            showMessage('No matching people found.');
            return;
        }

        results.forEach(function(person, index) {
            const link = document.createElement('a');
            const params = new URLSearchParams({ q: person.username });
            link.href = document.body.dataset.siteUrl + '/friends.php?' + params.toString();
            link.id = 'people-suggestion-' + index;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', 'false');
            link.dataset.suggestionIndex = String(index);
            link.className = 'block px-4 py-3 text-sm transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';

            const name = document.createElement('span');
            name.className = 'block font-bold text-ink';
            name.textContent = person.full_name;

            const details = document.createElement('span');
            details.className = 'mt-1 block text-slate-500';
            details.textContent = '@' + person.username + ' · ' + (person.role === 'staff' ? 'Teacher/Staff' : person.role);

            link.append(name, details);
            list.appendChild(link);
        });

        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
        status.textContent = results.length + ' matching people found.';
    };

    const setActiveOption = function(index) {
        const options = list.querySelectorAll('[role="option"]');
        if (!options.length) return;

        activeIndex = (index + options.length) % options.length;
        options.forEach(function(option, optionIndex) {
            const isActive = optionIndex === activeIndex;
            option.setAttribute('aria-selected', String(isActive));
            option.classList.toggle('bg-slate-100', isActive);
        });
        input.setAttribute('aria-activedescendant', options[activeIndex].id);
    };

    input.addEventListener('input', function() {
        window.clearTimeout(timer);
        if (controller) controller.abort();

        const query = input.value.trim();
        if (query.length < 2) {
            closeSuggestions();
            status.textContent = query.length ? 'Type at least two characters to search.' : '';
            return;
        }

        timer = window.setTimeout(async function() {
            controller = new AbortController();
            showMessage('Searching people…');
            try {
                const url = document.body.dataset.siteUrl + '/api/people-search.php?q=' + encodeURIComponent(query);
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'People search is temporarily unavailable.');
                }
                renderResults(payload.results);
            } catch (error) {
                if (error.name === 'AbortError') return;
                showMessage(error.message || 'People search is temporarily unavailable.');
            }
        }, 200);
    });

    input.addEventListener('keydown', function(event) {
        const options = list.querySelectorAll('[role="option"]');
        if (event.key === 'ArrowDown' && options.length) {
            event.preventDefault();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp' && options.length) {
            event.preventDefault();
            setActiveOption(activeIndex < 0 ? options.length - 1 : activeIndex - 1);
        } else if (event.key === 'Escape') {
            closeSuggestions();
        } else if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
            event.preventDefault();
            window.location.href = options[activeIndex].href;
        }
    });

    input.addEventListener('focus', function() {
        if (input.value.trim().length >= 2 && list.childElementCount) {
            list.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }
    });

    document.addEventListener('click', function(event) {
        if (!list.contains(event.target) && event.target !== input) closeSuggestions();
    });
});
