//== BnineFilesBundle - Simple Modal System ====================================
var _bnineCurrentInput = null;
var _bnineActiveModal = null;

function BnineModalOpen(opts) {
    BnineModalClose();

    var id = opts.id || 'bnine-modal-' + Date.now();
    var title = opts.title || '';
    var url = opts.url || '';
    var height = opts.height || '600px';

    var overlay = document.createElement('div');
    overlay.className = 'bnine-overlay';
    overlay.id = id;

    overlay.innerHTML =
        '<div class="bnine-dialog">' +
        '<div class="bnine-dialog-header">' +
        '<span class="bnine-dialog-title">' + title + '</span>' +
        '<button class="bnine-dialog-close" onclick="BnineModalClose()">&times;</button>' +
        '</div>' +
        '<div class="bnine-dialog-body">' +
        '<iframe frameborder="0" width="100%" height="' + height + '"></iframe>' +
        '</div>' +
        '</div>';

    document.body.appendChild(overlay);
    _bnineActiveModal = overlay;

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) BnineModalClose();
    });

    var iframe = overlay.querySelector('iframe');
    iframe.src = url;

    requestAnimationFrame(function () {
        overlay.classList.add('bnine-overlay-open');
    });
}

function BnineModalClose() {
    var overlays = document.querySelectorAll('.bnine-overlay');
    overlays.forEach(function (el) {
        el.classList.remove('bnine-overlay-open');
        setTimeout(function () { el.remove(); }, 200);
    });
    _bnineActiveModal = null;
    _bnineCurrentInput = null;
    document.dispatchEvent(new Event('bnine-modal-closed'));
}

//== Icon Upload Widget =======================================================
$(document).ready(function () {
    $('.icon-input').each(function () {
        var $input = $(this);
        var $id = $input.attr('id') || 'id';
        var value = $input.val() || '';
        var label = $input.data('icon-label') || 'Icon';
        var uploadUrl = $input.data('upload-url') || '';

        if ($input.parent().hasClass('icon-wrapper')) return;

        var $wrapper = $('<div class="text-center d-flex flex-column align-items-center mb-3 icon-wrapper"></div>');
        $input.wrap($wrapper);

        var $preview = $('<img id="' + $id + '_img" class="bigavatar mb-2" style="background-color: var(--bs-dark);">');
        $preview.attr('src', value ? (value.startsWith('/') ? value : '/' + value) : '');
        if (!value) $preview.css('display', 'none');
        $input.parent().prepend($preview);

        if (uploadUrl) {
            var $btn = $('<a class="btn btn-info" style="max-width:100%; margin-bottom:15px;"></a>');
            $btn.attr('onclick', "BnineModalOpen({id:'bnine-modal-" + $id + "',title:'" + label + "',url:'" + uploadUrl + "'});_bnineCurrentInput='" + $id + "';");
            $btn.attr('title', 'Ajouter ' + label);
            $btn.text('Modifier');
            $input.parent().append($btn);
        }

        $input.on('change', function () {
            var val = $(this).val();
            if (val) {
                var src = val.startsWith('/') ? val : '/' + val;
                $preview.attr('src', src).show();
            } else {
                $preview.hide();
            }
        });
    });
});

window.imageUploadDone = function (filepath, fileUrl) {
    if (fileUrl && _bnineCurrentInput) {
        var $input = $('#' + _bnineCurrentInput);
        var $wrapper = $input.closest('.icon-wrapper');
        $input.val(fileUrl).trigger('change');
        $wrapper.find('img').first().attr('src', fileUrl).show();
    }
    BnineModalClose();
};

window.BnineModalOpen = BnineModalOpen;
window.BnineModalClose = BnineModalClose;
