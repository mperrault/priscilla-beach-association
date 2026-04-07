document.addEventListener('DOMContentLoaded', function () {
    var directoryRoots = document.querySelectorAll('[data-pba-member-directory]');

    if (!directoryRoots.length || typeof pbaMemberDirectory === 'undefined') {
        return;
    }

    directoryRoots.forEach(function (root) {
        var form = root.querySelector('[data-pba-member-directory-form]');
        var input = root.querySelector('[data-pba-member-directory-input]');
        var clearButton = root.querySelector('[data-pba-member-directory-clear]');
        var feedback = root.querySelector('[data-pba-member-directory-feedback]');
        var results = root.querySelector('[data-pba-member-directory-results]');
        var activeRequest = null;

        if (!form || !input || !results) {
            return;
        }

        function setFeedback(message, state) {
            if (!feedback) {
                return;
            }

            feedback.textContent = message || '';
            feedback.classList.remove('is-loading', 'is-error');

            if (state) {
                feedback.classList.add(state);
            }
        }

        function setLoading(isLoading) {
            results.classList.toggle('is-loading', !!isLoading);
        }

        function updateClearButton() {
            if (!clearButton) {
                return;
            }

            clearButton.hidden = input.value.trim() === '';
        }

        function updateUrl(searchValue) {
            if (!results.dataset.baseUrl || !window.history || !window.history.replaceState) {
                return;
            }

            var url = new URL(results.dataset.baseUrl, window.location.origin);

            if (searchValue !== '') {
                url.searchParams.set('directory_search', searchValue);
            }

            window.history.replaceState({}, '', url.toString());
        }

        function bindDirectoryGrid() {
            var table = results.querySelector('[data-pba-directory-table]');
            var tbody = results.querySelector('[data-pba-directory-tbody]');
            var cardContainer = results.querySelector('[data-pba-directory-cards]');
            var pageSizeSelect = results.querySelector('[data-pba-directory-page-size]');
            var prevButton = results.querySelector('[data-pba-directory-prev]');
            var nextButton = results.querySelector('[data-pba-directory-next]');
            var pagesWrap = results.querySelector('[data-pba-directory-pages]');
            var summary = results.querySelector('[data-pba-directory-pagination-summary]');
            var sortButtons = results.querySelectorAll('.pba-member-directory-sort');

            if (!tbody || !cardContainer || !pageSizeSelect || !prevButton || !nextButton || !pagesWrap) {
                return;
            }

            var tableRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            var cards = Array.prototype.slice.call(cardContainer.querySelectorAll('.pba-member-directory-card'));

            if (!tableRows.length) {
                return;
            }

            var state = {
                page: 1,
                pageSize: parseInt(pageSizeSelect.value, 10) || 25,
                sortKey: 'name',
                sortDirection: 'asc'
            };

            function getValue(element, key) {
                return (element.getAttribute('data-' + key) || '').toLowerCase();
            }

            function compareValues(a, b) {
                if (a < b) {
                    return -1;
                }
                if (a > b) {
                    return 1;
                }
                return 0;
            }

            function sortData() {
                tableRows.sort(function (a, b) {
                    var aValue = getValue(a, state.sortKey);
                    var bValue = getValue(b, state.sortKey);
                    var result = compareValues(aValue, bValue);
                    return state.sortDirection === 'asc' ? result : result * -1;
                });

                cards.sort(function (a, b) {
                    var aValue = getValue(a, state.sortKey);
                    var bValue = getValue(b, state.sortKey);
                    var result = compareValues(aValue, bValue);
                    return state.sortDirection === 'asc' ? result : result * -1;
                });
            }

            function renderPageButtons(totalPages) {
                pagesWrap.innerHTML = '';

                var maxButtons = 7;
                var start = Math.max(1, state.page - 3);
                var end = Math.min(totalPages, start + maxButtons - 1);

                if ((end - start + 1) < maxButtons) {
                    start = Math.max(1, end - maxButtons + 1);
                }

                for (var i = start; i <= end; i += 1) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'pba-member-directory-page-button' + (i === state.page ? ' is-active' : '');
                    button.textContent = String(i);
                    button.setAttribute('data-page', String(i));

                    button.addEventListener('click', function () {
                        state.page = parseInt(this.getAttribute('data-page'), 10) || 1;
                        render();
                    });

                    pagesWrap.appendChild(button);
                }
            }

            function render() {
                sortData();

                tbody.innerHTML = '';
                cardContainer.innerHTML = '';

                var totalRows = tableRows.length;
                var totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));

                if (state.page > totalPages) {
                    state.page = totalPages;
                }

                var startIndex = (state.page - 1) * state.pageSize;
                var endIndex = Math.min(startIndex + state.pageSize, totalRows);
                var visibleRows = tableRows.slice(startIndex, endIndex);
                var visibleCards = cards.slice(startIndex, endIndex);

                visibleRows.forEach(function (row) {
                    tbody.appendChild(row);
                });

                visibleCards.forEach(function (card) {
                    cardContainer.appendChild(card);
                });

                prevButton.disabled = state.page <= 1;
                nextButton.disabled = state.page >= totalPages;

                renderPageButtons(totalPages);

                if (summary) {
                    summary.textContent = 'Showing ' + (totalRows === 0 ? 0 : (startIndex + 1)) + '–' + endIndex + ' of ' + totalRows;
                }

                sortButtons.forEach(function (button) {
                    button.classList.remove('is-active', 'is-asc', 'is-desc');

                    if (button.getAttribute('data-sort-key') === state.sortKey) {
                        button.classList.add('is-active');
                        button.classList.add(state.sortDirection === 'asc' ? 'is-asc' : 'is-desc');
                    }
                });
            }

            sortButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var key = button.getAttribute('data-sort-key');

                    if (state.sortKey === key) {
                        state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sortKey = key;
                        state.sortDirection = 'asc';
                    }

                    state.page = 1;
                    render();
                });
            });

            pageSizeSelect.addEventListener('change', function () {
                state.pageSize = parseInt(pageSizeSelect.value, 10) || 25;
                state.page = 1;
                render();
            });

            prevButton.addEventListener('click', function () {
                if (state.page > 1) {
                    state.page -= 1;
                    render();
                }
            });

            nextButton.addEventListener('click', function () {
                var totalPages = Math.max(1, Math.ceil(tableRows.length / state.pageSize));
                if (state.page < totalPages) {
                    state.page += 1;
                    render();
                }
            });

            render();
        }

        function submitSearch(searchValue) {
            var trimmed = (searchValue || '').trim();

            updateClearButton();
            setFeedback(pbaMemberDirectory.strings.searching, 'is-loading');
            setLoading(true);

            if (activeRequest && typeof activeRequest.abort === 'function') {
                activeRequest.abort();
            }

            activeRequest = new XMLHttpRequest();
            activeRequest.open('POST', pbaMemberDirectory.ajaxUrl, true);
            activeRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

            activeRequest.onreadystatechange = function () {
                if (activeRequest.readyState !== 4) {
                    return;
                }

                setLoading(false);

                if (activeRequest.status < 200 || activeRequest.status >= 300) {
                    setFeedback(pbaMemberDirectory.strings.error, 'is-error');
                    return;
                }

                var response;

                try {
                    response = JSON.parse(activeRequest.responseText);
                } catch (error) {
                    setFeedback(pbaMemberDirectory.strings.error, 'is-error');
                    return;
                }

                if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                    setFeedback(pbaMemberDirectory.strings.error, 'is-error');
                    return;
                }

                results.innerHTML = response.data.html;
                updateUrl(trimmed);
                bindDirectoryGrid();

                if (trimmed !== '') {
                    setFeedback(pbaMemberDirectory.strings.resultsFor + ' "' + trimmed + '".', '');
                } else {
                    setFeedback('', '');
                }
            };

            activeRequest.onerror = function () {
                setLoading(false);
                setFeedback(pbaMemberDirectory.strings.error, 'is-error');
            };

            activeRequest.send(
                'action=' + encodeURIComponent(pbaMemberDirectory.action) +
                '&nonce=' + encodeURIComponent(pbaMemberDirectory.nonce) +
                '&search=' + encodeURIComponent(trimmed)
            );
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitSearch(input.value);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitSearch(input.value);
            }
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                submitSearch('');
            });
        }

        updateClearButton();
        bindDirectoryGrid();
    });
});