(function () {
    function initLightbox() {
        var lightbox = document.querySelector('[data-pba-lightbox]');
        if (!lightbox) {
            return;
        }

        var image = lightbox.querySelector('[data-pba-lightbox-image]');
        var title = lightbox.querySelector('[data-pba-lightbox-title]');
        var meta = lightbox.querySelector('[data-pba-lightbox-meta]');
        var caption = lightbox.querySelector('[data-pba-lightbox-caption]');
        var closeButtons = lightbox.querySelectorAll('[data-pba-lightbox-close]');
        var lastFocused = null;

        function openLightbox(button) {
            if (!button || !image || !title || !meta || !caption) {
                return;
            }

            lastFocused = document.activeElement;

            var src = button.getAttribute('data-photo-src') || '';
            var photoTitle = button.getAttribute('data-photo-title') || '';
            var photoCaption = button.getAttribute('data-photo-caption') || '';
            var photoMeta = button.getAttribute('data-photo-meta') || '';

            image.setAttribute('src', src);
            image.setAttribute('alt', photoTitle);

            title.textContent = photoTitle;
            meta.textContent = photoMeta;
            caption.textContent = photoCaption;

            meta.style.display = photoMeta ? '' : 'none';
            caption.style.display = photoCaption ? '' : 'none';

            lightbox.hidden = false;
            document.documentElement.classList.add('pba-photo-lightbox-open');

            var closeButton = lightbox.querySelector('.pba-photo-lightbox-close');
            if (closeButton) {
                closeButton.focus();
            }
        }

        function closeLightbox() {
            lightbox.hidden = true;
            document.documentElement.classList.remove('pba-photo-lightbox-open');

            if (image) {
                image.setAttribute('src', '');
                image.setAttribute('alt', '');
            }

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        document.addEventListener('click', function (event) {
            var openButton = event.target.closest('[data-pba-lightbox-open]');
            if (openButton) {
                event.preventDefault();
                openLightbox(openButton);
                return;
            }

            var closeButton = event.target.closest('[data-pba-lightbox-close]');
            if (closeButton && lightbox.contains(closeButton)) {
                event.preventDefault();
                closeLightbox();
            }
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                closeLightbox();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (lightbox.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeLightbox();
            }
        });
    }

    function initFilePickers() {
        var pickers = document.querySelectorAll('[data-pba-photo-file-picker]');
        if (!pickers.length) {
            return;
        }

        pickers.forEach(function (picker) {
            var input = picker.querySelector('.pba-photo-native-file-input');
            var fileName = picker.querySelector('[data-pba-photo-file-name]');

            if (!input || !fileName) {
                return;
            }

            input.addEventListener('change', function () {
                if (input.files && input.files.length > 0) {
                    fileName.textContent = input.files[0].name;
                    picker.classList.add('has-file');
                } else {
                    fileName.textContent = 'No file selected';
                    picker.classList.remove('has-file');
                }
            });
        });
    }

    function init() {
        initLightbox();
        initFilePickers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();