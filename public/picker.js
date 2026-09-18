/*
 * calmfox/inpost-sylius — wybór paczkomatu w kroku dostawy.
 * Bez zależności i bez budowania: plik trafia do public/bundles przez assets:install.
 * Stan trzyma ukryte pole formularza; ten skrypt tylko pomaga je wypełnić. Brak punktu
 * zgłasza serwer jako zwykły błąd formularza, więc nic tu nie blokuje przycisku „Dalej".
 */
(function () {
    'use strict';

    function init(root) {
        if (root.dataset.calmfoxInpostReady === '1') return;
        root.dataset.calmfoxInpostReady = '1';

        var methods = JSON.parse(root.dataset.methods || '[]');
        var form = root.closest('form') || document;
        var value = root.querySelector('[data-calmfox-inpost-value]');
        var query = root.querySelector('[data-calmfox-inpost-query]');
        var button = root.querySelector('[data-calmfox-inpost-search]');
        var results = root.querySelector('[data-calmfox-inpost-results]');
        var message = root.querySelector('[data-calmfox-inpost-message]');
        var selected = root.querySelector('[data-calmfox-inpost-selected]');
        var selectedName = root.querySelector('[data-calmfox-inpost-selected-name]');
        var selectedAddress = root.querySelector('[data-calmfox-inpost-selected-address]');
        var searchedOnce = false;
        var pending = 0;

        function radios() {
            return Array.prototype.filter.call(form.querySelectorAll('input[type="radio"]'), function (r) {
                return r.name === root.dataset.methodField;
            });
        }

        function say(text) {
            message.textContent = text || '';
            message.hidden = !text;
        }

        function choose(point) {
            value.value = point.name;
            selectedName.textContent = point.name;
            selectedAddress.textContent = point.address || '';
            selected.hidden = false;
            Array.prototype.forEach.call(results.children, function (li) {
                li.classList.toggle('is-selected', li.dataset.point === point.name);
            });
        }

        function render(points) {
            results.innerHTML = '';
            points.forEach(function (point) {
                var li = document.createElement('li');
                li.className = 'calmfox-inpost__point' + (point.name === value.value ? ' is-selected' : '');
                li.dataset.point = point.name;

                var pick = document.createElement('button');
                pick.type = 'button';
                pick.className = 'calmfox-inpost__pick';

                var name = document.createElement('strong');
                name.textContent = point.name;
                var address = document.createElement('span');
                address.className = 'calmfox-inpost__address';
                address.textContent = point.address || '';
                var meta = document.createElement('span');
                meta.className = 'calmfox-inpost__meta';
                var bits = [];
                if (typeof point.distance === 'number') {
                    bits.push(point.distance < 1000 ? point.distance + ' m' : (point.distance / 1000).toFixed(1).replace('.', ',') + ' km');
                }
                if (point.description) bits.push(point.description);
                if (point.openingHours) bits.push(point.openingHours);
                meta.textContent = bits.join(' · ');

                pick.appendChild(name);
                pick.appendChild(address);
                pick.appendChild(meta);
                pick.setAttribute('aria-label', root.dataset.textChoose + ' ' + point.name);
                pick.addEventListener('click', function () { choose(point); });

                li.appendChild(pick);
                results.appendChild(li);
            });
        }

        function search() {
            var q = query.value.trim();
            if (q.length < 3) { say(root.dataset.textQuery); return; }

            var ticket = ++pending;
            say(root.dataset.textLoading);

            fetch(root.dataset.url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.json().then(function (data) { return { status: response.status, data: data }; }); })
                .then(function (result) {
                    if (ticket !== pending) return;
                    if (result.status === 422) { render([]); say(root.dataset.textQuery); return; }
                    if (result.status >= 400) { render([]); say(root.dataset.textError); return; }
                    render(result.data.points || []);
                    say((result.data.points || []).length ? '' : root.dataset.textEmpty);
                })
                .catch(function () { if (ticket === pending) { render([]); say(root.dataset.textError); } });
        }

        function sync() {
            var active = radios().some(function (r) { return r.checked && methods.indexOf(r.value) !== -1; });
            root.hidden = !active;

            // Pierwsze pokazanie: od razu najbliższe punkty dla kodu pocztowego z adresu dostawy.
            if (active && !searchedOnce && !value.value && root.dataset.postcode) {
                searchedOnce = true;
                query.value = root.dataset.postcode;
                search();
            }
        }

        button.addEventListener('click', search);
        query.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); search(); }
        });
        form.addEventListener('change', function (event) {
            if (event.target && event.target.name === root.dataset.methodField) sync();
        });

        sync();
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-calmfox-inpost]'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('turbo:load', boot);
    document.addEventListener('live:render', boot);
})();
