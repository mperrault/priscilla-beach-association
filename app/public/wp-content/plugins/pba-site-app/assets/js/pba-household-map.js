document.addEventListener('DOMContentLoaded', function () {
    var mapEl = document.getElementById('pba-household-map-canvas');
    var searchEl = document.getElementById('pba-household-map-search');
    var statusFilterEl = document.getElementById('pba-household-map-filter-status');
    var occupancyFilterEl = document.getElementById('pba-household-map-filter-occupancy');
    var statusBoxEl = document.getElementById('pba-household-map-status');

    if (!mapEl || typeof L === 'undefined' || typeof pbaHouseholdMap === 'undefined') {
        return;
    }

    mapEl.style.visibility = 'hidden';

    function forceGlobalCursorReset() {
        document.documentElement.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting');
        document.body.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting');

        document.documentElement.style.removeProperty('cursor');
        document.body.style.removeProperty('cursor');

        document.documentElement.style.setProperty('cursor', 'default', 'important');
        document.body.style.setProperty('cursor', 'default', 'important');

        window.setTimeout(function () {
            document.documentElement.style.removeProperty('cursor');
            document.body.style.removeProperty('cursor');
        }, 150);
    }

    function forceMapCursorReset() {
        forceGlobalCursorReset();

        mapEl.style.cursor = '';

        var nodes = mapEl.querySelectorAll(
            '.leaflet-container, .leaflet-container *, .leaflet-popup, .leaflet-popup *, .leaflet-control, .leaflet-control *'
        );

        nodes.forEach(function (node) {
            node.style.removeProperty('cursor');
        });
    }

    var map = L.map('pba-household-map-canvas', {
        scrollWheelZoom: true
    }).setView(
        [parseFloat(pbaHouseholdMap.mapCenterLat), parseFloat(pbaHouseholdMap.mapCenterLng)],
        parseInt(pbaHouseholdMap.mapZoom, 10)
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var markerLayer = L.layerGroup().addTo(map);
    var allRows = [];

    function setStatus(message, isError) {
        if (!statusBoxEl) {
            return;
        }

        if (!message) {
            statusBoxEl.textContent = '';
            statusBoxEl.classList.remove('active', 'error');
            return;
        }

        statusBoxEl.textContent = message;
        statusBoxEl.classList.add('active');

        if (isError) {
            statusBoxEl.classList.add('error');
        } else {
            statusBoxEl.classList.remove('error');
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildPopupHtml(row) {
        var html = '<div class="pba-household-map-popup">';
        html += '<div class="pba-household-map-popup-title">' + escapeHtml(row.address || 'Unknown address') + '</div>';

        if (row.household_status) {
            html += '<div class="pba-household-map-popup-row"><strong>Status:</strong> ' + escapeHtml(row.household_status) + '</div>';
        }

        if (row.household_admin_name) {
            html += '<div class="pba-household-map-popup-row"><strong>House Admin:</strong> ' + escapeHtml(row.household_admin_name) + '</div>';
        }

        if (row.owner_name_raw) {
            html += '<div class="pba-household-map-popup-row"><strong>Owner:</strong> ' + escapeHtml(row.owner_name_raw) + '</div>';
        }

        html += '<div class="pba-household-map-popup-row"><strong>Owner occupied:</strong> ' + (row.owner_occupied ? 'Yes' : 'No') + '</div>';

        if (row.household_admin_email) {
            html += '<div class="pba-household-map-popup-row"><strong>Email:</strong> ' + escapeHtml(row.household_admin_email) + '</div>';
        }

        if (row.detail_url) {
            html += '<a class="pba-household-map-popup-link" href="' + escapeHtml(row.detail_url) + '">Open household</a>';
        }

        html += '</div>';
        return html;
    }

    function getMarkerColor(row) {
        if (row.owner_occupied) {
            return '#1d4ed8';
        }

        if ((row.household_status || '').toLowerCase() === 'active') {
            return '#0d3b66';
        }

        return '#6b7280';
    }

    function filterRows(rows) {
        var search = searchEl ? searchEl.value.trim().toLowerCase() : '';
        var statusFilter = statusFilterEl ? statusFilterEl.value : '';
        var occupancyFilter = occupancyFilterEl ? occupancyFilterEl.value : '';

        return rows.filter(function (row) {
            var haystack = [
                row.address || '',
                row.street_name || '',
                row.household_admin_name || '',
                row.owner_name_raw || ''
            ].join(' ').toLowerCase();

            if (search && haystack.indexOf(search) === -1) {
                return false;
            }

            if (statusFilter && (row.household_status || '') !== statusFilter) {
                return false;
            }

            if (occupancyFilter === 'owner_occupied' && !row.owner_occupied) {
                return false;
            }

            if (occupancyFilter === 'not_owner_occupied' && row.owner_occupied) {
                return false;
            }

            return true;
        });
    }

    function renderRows() {
        markerLayer.clearLayers();

        var rows = filterRows(allRows);
        var bounds = [];

        rows.forEach(function (row) {
            var lat = parseFloat(row.latitude);
            var lng = parseFloat(row.longitude);

            if (!isFinite(lat) || !isFinite(lng)) {
                return;
            }

            var color = getMarkerColor(row);

            var marker = L.circleMarker([lat, lng], {
                radius: 6,
                color: '#ffffff',
                weight: 2,
                fillColor: color,
                fillOpacity: 0.95
            });

            marker.bindPopup(buildPopupHtml(row));
            marker.addTo(markerLayer);
            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, {
                padding: [24, 24],
                maxZoom: 18
            });
            setStatus(rows.length + ' household' + (rows.length === 1 ? '' : 's') + ' shown.', false);
        } else {
            map.setView(
                [parseFloat(pbaHouseholdMap.mapCenterLat), parseFloat(pbaHouseholdMap.mapCenterLng)],
                parseInt(pbaHouseholdMap.mapZoom, 10)
            );
            setStatus('No households matched the current filters.', false);
        }

        mapEl.style.visibility = 'visible';
        map.invalidateSize();
        forceMapCursorReset();
    }

    function loadRows() {
        setStatus('Loading household map...', false);
        forceMapCursorReset();

        fetch(pbaHouseholdMap.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'pba_get_household_map_data',
                nonce: pbaHouseholdMap.nonce
            }).toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result || !result.success || !Array.isArray(result.data)) {
                    var message = 'Unable to load household map data.';
                    if (result && result.data && result.data.message) {
                        message = result.data.message;
                    }
                    throw new Error(message);
                }

                allRows = result.data;
                renderRows();
                forceMapCursorReset();
            })
            .catch(function (error) {
                setStatus(error && error.message ? error.message : 'Unable to load household map data.', true);
                mapEl.style.visibility = 'visible';
                forceMapCursorReset();
            });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            renderRows();
            forceMapCursorReset();
        });
    }

    if (statusFilterEl) {
        statusFilterEl.addEventListener('change', function () {
            renderRows();
            forceMapCursorReset();
        });
    }

    if (occupancyFilterEl) {
        occupancyFilterEl.addEventListener('change', function () {
            renderRows();
            forceMapCursorReset();
        });
    }

    map.on('popupopen', forceMapCursorReset);
    map.on('popupclose', forceMapCursorReset);
    map.on('click', forceMapCursorReset);
    map.on('moveend', forceMapCursorReset);
    map.on('zoomend', forceMapCursorReset);
    map.on('load', forceMapCursorReset);

    mapEl.addEventListener('click', function (event) {
        if (
            event.target.closest('.leaflet-popup-close-button') ||
            event.target.closest('.leaflet-popup') ||
            event.target.closest('.leaflet-control')
        ) {
            window.setTimeout(forceMapCursorReset, 0);
        }
    });

    loadRows();
});