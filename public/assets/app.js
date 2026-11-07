(function () {
    var toggle = document.getElementById('theme-toggle');
    var theme = 'light';
    var each = function (selector, callback, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), callback);
    };
    try { theme = localStorage.getItem('ecotrade-theme') === 'dark' ? 'dark' : 'light'; } catch (error) { }
    function applyTheme() {
        document.documentElement.setAttribute('data-theme', theme);
        toggle.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
    }
    toggle.hidden = false;
    applyTheme();
    toggle.addEventListener('click', function () {
        theme = theme === 'dark' ? 'light' : 'dark';
        applyTheme();
        try { localStorage.setItem('ecotrade-theme', theme); } catch (error) { }
    });
    var loading = document.getElementById('loading-message');
    each('[data-password-toggle]', function (button) {
        var field = document.getElementById(button.getAttribute('aria-controls'));
        button.hidden = false;
        button.addEventListener('click', function () {
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute('data-visible', String(show));
            button.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + button.getAttribute('data-field-label'));
        });
    });
    function busy() {
        loading.hidden = false;
        document.querySelector('main').setAttribute('aria-busy', 'true');
    }
    var dialog = document.getElementById('confirm-action');
    var dialogTitle = document.getElementById('confirm-title');
    var dialogDescription = document.getElementById('confirm-description');
    var dialogButton = dialog.querySelector('[value="confirm"]');
    var pendingAction = null;
    function confirmAction(title, detail, action, danger, callback) {
        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(title + (detail ? '\n\n' + detail : ''))) callback();
            return;
        }
        if (dialog.open || pendingAction) return;
        pendingAction = callback;
        dialogTitle.textContent = title;
        dialogDescription.textContent = detail || '';
        dialogDescription.hidden = !detail;
        dialogButton.textContent = action;
        dialogButton.className = danger ? 'button-danger' : 'button-primary';
        dialog.returnValue = '';
        dialog.showModal();
    }
    dialog.addEventListener('close', function () {
        var callback = pendingAction;
        pendingAction = null;
        if (dialog.returnValue === 'confirm' && callback) callback();
    });
    function confirmForm(form, submit) {
        confirmAction(form.getAttribute('data-confirm'), form.getAttribute('data-confirm-detail'),
            form.getAttribute('data-confirm-action'), form.hasAttribute('data-confirm-danger'), submit);
    }
    function submitForm(form) {
        busy();
        each('button[type="submit"]', function (button) { button.disabled = true; }, form);
        HTMLFormElement.prototype.submit.call(form);
    }
    each('[data-email-domains]', function (field) {
        var domains = field.getAttribute('data-email-domains').split(' ');
        function validateDomain() {
            field.setCustomValidity('');
            if (field.value && field.validity.valid && domains.indexOf(field.value.split('@').pop().toLowerCase()) === -1) {
                field.setCustomValidity('Use an email from a supported provider such as Gmail, Outlook, Yahoo, iCloud or Proton Mail.');
            }
        }
        field.addEventListener('input', validateDomain);
        field.addEventListener('change', validateDomain);
    });
    each('form:not([method="dialog"])', function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.hasAttribute('data-confirm')) {
                event.preventDefault();
                confirmForm(form, function () { submitForm(form); });
            } else {
                busy();
                each('button[type="submit"]', function (button) { button.disabled = true; }, form);
            }
        });
    });
    each('a[href]', function (link) {
        link.addEventListener('click', function (event) {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            var formId = link.getAttribute('data-confirm-form');
            if (formId) {
                event.preventDefault();
                var form = document.getElementById(formId);
                confirmForm(form, function () { submitForm(form); });
            } else if (!link.hash && !link.hasAttribute('download')) {
                busy();
            }
        });
    });
    var draftButton = document.getElementById('description-draft');
    if (draftButton) {
        var description = document.getElementById('description');
        var feedback = document.getElementById('description-feedback');
        var photos = document.getElementById('photos');
        var category = document.getElementById('category_id');
        function showFeedback(message) {
            feedback.textContent = message;
            feedback.hidden = false;
        }
        function photoColour(image, labels) {
            var canvas = document.createElement('canvas');
            canvas.width = canvas.height = 24;
            var context = canvas.getContext('2d');
            if (!context) return '';
            context.drawImage(image, 0, 0, 24, 24);
            var pixels = context.getImageData(0, 0, 24, 24).data;
            var counts = [0, 0, 0, 0, 0, 0, 0, 0];
            var limits = [16, 43, 68, 160, 198, 258, 305, 347];
            for (var i = 0; i < pixels.length; i += 4) {
                var r = pixels[i], g = pixels[i + 1], b = pixels[i + 2];
                var max = Math.max(r, g, b);
                var range = max - Math.min(r, g, b);
                if (pixels[i + 3] < 128 || max < 65 || range < 45) continue;
                var hue = 60 * (max === r ? (g - b) / range : max === g ? 2 + (b - r) / range : 4 + (r - g) / range);
                hue = (hue + 360) % 360;
                var bucket = 0;
                while (bucket < limits.length && hue >= limits[bucket]) bucket++;
                counts[bucket % counts.length]++;
            }
            var leading = Math.max.apply(null, counts);
            return leading >= 145 ? labels[counts.indexOf(leading)] : '';
        }
        function draft() {
            var title = document.getElementById('title').value.trim();
            if (!category.value || title.length < 5) {
                showFeedback('Choose a category and enter a title before generating a draft.');
                (category.value ? document.getElementById('title') : category).focus();
                return;
            }
            var categoryName = category.options[category.selectedIndex].textContent;
            var conditionChoice = document.querySelector('[name="item_condition"]:checked');
            var listingChoice = document.querySelector('[name="listing_type"]:checked');
            if (!conditionChoice || !listingChoice) {
                showFeedback('Choose the condition and listing type before generating a draft.');
                return;
            }
            var condition = conditionChoice.value;
            var exchange = listingChoice.value === 'exchange';
            var malay = document.getElementById('source_language').value === 'ms';
            var conditions = malay ? { 'new': 'Baru', like_new: 'Seperti baru', good: 'Baik', fair: 'Sederhana' }
                : { 'new': 'New', like_new: 'Like new', good: 'Good', fair: 'Fair' };
            var colourLabels = malay ? ['merah', 'jingga', 'kuning', 'hijau', 'biru kehijauan', 'biru', 'ungu', 'merah jambu']
                : ['red', 'orange', 'yellow', 'green', 'teal', 'blue', 'purple', 'pink'];
            var draftText = title + ' (' + categoryName + '). ' +
                (malay ? 'Keadaan barang: ' : 'Condition: ') + conditions[condition] + '. ' +
                (malay ? (exchange ? 'Terbuka untuk pertukaran.' : 'Barang ini ditawarkan secara percuma.')
                    : (exchange ? 'Available to exchange for another study item.' : 'Available for free.')) + '\n\n';
            var previousDescription = description.value;
            function finish(colour, hasPhoto, readable) {
                draftButton.disabled = false;
                if (description.value !== previousDescription) {
                    showFeedback('Your edits were kept. Generate again to replace them.');
                    return;
                }
                if (hasPhoto) {
                    if (colour) draftText += malay ? 'Gambar menunjukkan tona ' + colour + '. ' : 'The photo shows mostly ' + colour + ' tones. ';
                    draftText += malay ? 'Rujuk gambar untuk melihat rupa barang.' : 'See the listing photo for a closer look.';
                } else {
                    draftText += malay ? 'Mesej saya jika ingin tahu lebih lanjut.' : 'Message me if you would like more details.';
                }
                description.value = draftText;
                showFeedback(readable ? 'Draft ready. Check the photo details and edit before saving.'
                    : hasPhoto ? 'Draft ready. Add any details visible in your photo before saving.'
                    : 'Draft ready. Review and edit it before saving.');
                description.focus();
            }
            var file = photos.files && photos.files[0];
            var saved = document.querySelector('.saved-photos img');
            var removesPhotos = document.querySelector('[name="remove_photos"]:checked');
            var canPreview = file && file.size < 10000000 && window.URL && typeof URL.createObjectURL === 'function';
            var objectUrl = canPreview ? URL.createObjectURL(file) : '';
            var source = file ? objectUrl : (saved && !removesPhotos ? saved.src : '');
            if (!source) {
                finish('', !!file, false);
                return;
            }
            var image = new Image();
            draftButton.disabled = true;
            showFeedback('Reading the first photo…');
            image.onload = function () {
                var colour = '';
                try { colour = photoColour(image, colourLabels); } catch (error) { }
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                finish(colour, true, true);
            };
            image.onerror = function () {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                finish('', true, false);
            };
            image.src = source;
        }
        draftButton.hidden = false;
        document.getElementById('draft-help').hidden = false;
        draftButton.addEventListener('click', function () {
            if (description.value.trim()) confirmAction('Replace the description?', 'Generating a draft will replace the text in the description field.', 'Replace description', false, draft);
            else draft();
        });
    }
    window.addEventListener('pageshow', function () {
        loading.hidden = true;
        document.querySelector('main').removeAttribute('aria-busy');
        each('button[type="submit"]', function (button) {
            if (!button.hasAttribute('data-permanent-disabled')) button.disabled = false;
        });
    });
})();
