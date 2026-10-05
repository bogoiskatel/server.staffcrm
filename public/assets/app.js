'use strict';
(function () {
  // Preserve the current route while changing language through the CRM-style selector.
  document.querySelectorAll('.staff-language-select').forEach(function (select) {
    select.addEventListener('change', function () { window.location.assign(select.value); });
  });
  // Keep the user menu open while its language selector is being used.
  $('.user-dd').on('click', function (event) { event.stopPropagation(); });
  const settings = JSON.parse(document.getElementById('app-settings').textContent);
  const labels = settings.labels;
  let networkMap;
  // Build popups with text nodes so registry names can never become HTML.
  function popup(point) {
    const box = document.createElement('div');
    const title = document.createElement('strong'); title.textContent = point.name; box.appendChild(title);
    const source = document.createElement('small'); source.textContent = point.source === 'postcode_geocoding' ? labels.approximate : labels.church_location; box.appendChild(source);
    if (point.url) { const link = document.createElement('a'); link.href = point.url; link.textContent = labels.details; box.appendChild(link); }
    return box;
  }
  // Initialize a configurable tile layer and preserve usable points on tile failure.
  function createMap(element, points) {
    const map = L.map(element).setView([30, 15], 2);
    if (settings.tileUrl) {
      L.tileLayer(settings.tileUrl, { attribution: settings.attribution, maxZoom: 19 }).on('tileerror', function () {
        const notice = element.parentElement.querySelector('.map-error');
        if (notice) { notice.textContent = labels.map_unavailable; notice.hidden = false; }
      }).addTo(map);
    }
    const markers = points.map(function (point) { return L.marker([point.lat, point.lng]).addTo(map).bindPopup(popup(point)); });
    if (markers.length) { map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [30, 30], maxZoom: 12 }); }
    return map;
  }
  const network = document.getElementById('network-map');
  if (network) {
    fetch(network.dataset.mapUrl, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok || !response.headers.get('content-type').includes('application/json')) { throw new Error('Map data unavailable'); }
      return response.json();
    }).then(function (data) { networkMap = createMap(network, data.points); }).catch(function () {
      const notice = network.parentElement.querySelector('.map-error'); notice.textContent = labels.no_data; notice.hidden = false;
    });
  }
  const detail = document.getElementById('installation-map');
  if (detail) { createMap(detail, [JSON.parse(detail.dataset.point)]); }
  if (document.getElementById('crm-table')) {
    $('#crm-table').DataTable({ pageLength: 25, order: [[0, 'asc']], language: {
      search: labels.search, lengthMenu: labels.length, info: labels.info, infoEmpty: labels.no_data, emptyTable: labels.no_data,
      zeroRecords: labels.zero_records, infoFiltered: '', paginate: { previous: labels.previous, next: labels.next }
    }});
  }
  document.querySelectorAll('[data-tab]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      const tab = link.dataset.tab;
      document.querySelectorAll('[data-tab]').forEach(function (item) { item.classList.toggle('active', item === link); });
      document.getElementById('network-panel').hidden = tab !== 'network';
      document.getElementById('statistics-panel').hidden = tab !== 'statistics';
      document.querySelector('.period-form input[name="tab"]').value = tab;
      window.history.replaceState(null, '', link.href);
      if (networkMap) { networkMap.invalidateSize(); }
      $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
  });
}());
