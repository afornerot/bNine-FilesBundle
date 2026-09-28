//== BnineFilesBundle - Simple Modal System + CustomEvents ===================
// Le bundle dispatche des CustomEvents DOM standard (bnine:*) a chaque
// action notable. Les apps hotes s'abonnent via addEventListener :
//
//   document.addEventListener('bnine:upload:done', function(e) {
//       console.log('Upload :', e.detail);
//   });
//
// Les noms d'events sont exposes via window.BnineEvents (voir
// bninefiles-constants.js) ou en dure dans les chaines ci-dessous.

// Variable globales de compat (les apps hotes peuvent les consulter, mais
// preferent addEventListener).
var _bnineCurrentInput = null;
var _bnineActiveModal = null;
var _bnineOnCloseCallback = null;
var _bnineActiveWidgetId = null; // UUID unique par widget

function BnineModalOpen(opts) {
    BnineModalClose();

    var id = opts.id || 'bnine-modal-' + Date.now();
    var title = opts.title || '';
    var url = opts.url || '';
    var height = opts.height || '600px';
    _bnineOnCloseCallback = opts.onClose || null;
    _bnineCurrentInput = opts.currentInput || null;
    _bnineActiveWidgetId = opts.widgetId || _bnineActiveWidgetId || null;

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

    // Dispatcher le CustomEvent bnine:modal:open
    var detail = {
        id: id,
        title: title,
        url: url,
        height: height,
        currentInput: _bnineCurrentInput,
        origin: _bnineActiveWidgetId,
        endpoint: opts.endpoint || null,
    };
    if (typeof bnineDispatch === 'function') {
        bnineDispatch('bnine:modal:open', detail, document);
    } else {
        // Fallback si bninefiles-constants.js n'a pas ete charge
        var ev;
        try { ev = new CustomEvent('bnine:modal:open', { detail: detail, bubbles: true }); }
        catch (e) { ev = document.createEvent('CustomEvent'); ev.initCustomEvent('bnine:modal:open', true, false, detail); }
        document.dispatchEvent(ev);
    }
}

function BnineModalClose() {
    var closedModalId = _bnineActiveModal ? _bnineActiveModal.id : null;

    // Dispatcher bnine:modal:close AVANT l'animation
    if (typeof bnineDispatch === 'function') {
        bnineDispatch('bnine:modal:close', {
            id: closedModalId,
            origin: _bnineActiveWidgetId,
        }, document);
    } else {
        var evClose;
        try { evClose = new CustomEvent('bnine:modal:close', { detail: { id: closedModalId, origin: _bnineActiveWidgetId }, bubbles: true }); }
        catch (e) { evClose = document.createEvent('CustomEvent'); evClose.initCustomEvent('bnine:modal:close', true, false, { id: closedModalId, origin: _bnineActiveWidgetId }); }
        document.dispatchEvent(evClose);
    }

    var onCloseCb = _bnineOnCloseCallback;
    var overlays = document.querySelectorAll('.bnine-overlay');
    overlays.forEach(function (el) {
        el.classList.remove('bnine-overlay-open');
        setTimeout(function () { el.remove(); }, 200);
    });
    _bnineActiveModal = null;
    _bnineOnCloseCallback = null;
    _bnineCurrentInput = null;
    _bnineActiveWidgetId = null;

    // Dispatcher bnine:modal:closed (compat historique + nouveau format)
    document.dispatchEvent(new CustomEvent('bnine-modal-closed', { detail: { id: closedModalId } }));
    if (typeof bnineDispatch === 'function') {
        bnineDispatch('bnine:modal:closed', {
            id: closedModalId,
            origin: _bnineActiveWidgetId,
        }, document);
    }

    if (typeof onCloseCb === 'function') {
        onCloseCb();
    }
}

//== Icon Upload Widget =======================================================
$(document).ready(function () {
    // Fonction INTERNE au bundle : met a jour l'input + la preview apres
    // upload ou crop.
    function applyIconUploadResult(detail) {
        var currentInputId = _bnineCurrentInput;
        if (!currentInputId) return;

        var $input = document.getElementById(currentInputId);
        if (!$input) return;

        var $inputJq = window.jQuery ? window.jQuery($input) : null;

        var filepath = (detail && detail.path) || '';
        var fileUrl = (detail && detail.url) || '';

        if ($inputJq) {
            $inputJq.val(filepath).trigger('change');
        } else {
            $input.value = filepath;
            $input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Mettre a jour la preview img
        var previewImg = document.getElementById(currentInputId + '_img');
        if (previewImg && fileUrl) {
            previewImg.setAttribute('src', fileUrl);
            previewImg.style.display = '';
        }
    }

    // Listeners INTERNES au bundle : apres upload ou crop, mettre a jour
    // l'input + la preview associes au widget IconUploadType.
    // (Avant c'etait gere par window.imageUploadDone qui est supprime.)
    document.addEventListener('bnine:upload:done', function (e) {
        applyIconUploadResult(e.detail || {});
    });
    document.addEventListener('bnine:crop:done', function (e) {
        applyIconUploadResult(e.detail || {});
    });

    $('.icon-input').each(function () {
        var $input = $(this);
        var $id = $input.attr('id') || 'id';
        var value = $input.val() || '';
        var label = $input.data('icon-label') || 'Icon';
        var uploadUrl = $input.data('upload-url') || '';
        var previewMaxHeight = parseInt($input.data('preview-max-height'), 10) || 100;

        if ($input.parent().hasClass('icon-wrapper')) return;

        var $wrapper = $('<div class="text-center d-flex flex-column align-items-center mb-3 icon-wrapper"></div>');
        $input.wrap($wrapper);

        // Preview : hauteur max configurable via preview-max-height (defaut 100px)
        var $preview = $('<img id="' + $id + '_img" class="bigavatar mb-2" style="background-color: var(--bs-dark); max-height:' + previewMaxHeight + 'px;">');
        $preview.attr('src', value ? (value.startsWith('/') ? value : '/' + value) : '');
        if (!value) $preview.css('display', 'none');
        $input.parent().prepend($preview);

        if (uploadUrl) {
            // Generer un UUID unique par widget pour identifier l'origine
            var widgetId = (typeof bnineUuid === 'function') ? bnineUuid() : ('icon-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8));
            $input.attr('data-instance', widgetId);

            // Injecter l'origin dans l'URL d'upload pour que l'iframe puisse le recuperer
            var uploadUrlWithOrigin = uploadUrl;
            if (uploadUrlWithOrigin.indexOf('origin=') === -1) {
                uploadUrlWithOrigin += (uploadUrlWithOrigin.indexOf('?') === -1 ? '?' : '&') + 'origin=' + encodeURIComponent(widgetId);
            }

            var $btn = $('<a class="btn btn-info" style="max-width:100%; margin-bottom:15px;"></a>');
            $btn.attr('onclick', "BnineModalOpen({id:'bnine-modal-" + $id + "',title:'" + label + "',url:'" + uploadUrlWithOrigin + "',currentInput:'" + $id + "',widgetId:'" + widgetId + "',endpoint:'iconupload'});");
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

            // Dispatcher bnine:iconupload:change
            var detail = {
                value: val,
                inputId: $id,
                origin: $input.data('instance') || null,
            };
            if (typeof bnineDispatch === 'function') {
                bnineDispatch('bnine:iconupload:change', detail, document);
            } else {
                var ev;
                try { ev = new CustomEvent('bnine:iconupload:change', { detail: detail, bubbles: true }); }
                catch (e) { ev = document.createEvent('CustomEvent'); ev.initCustomEvent('bnine:iconupload:change', true, false, detail); }
                document.dispatchEvent(ev);
            }
        });
    });
});

// API publique : BnineModalOpen/Close utilitaires (pas des callbacks)
// window.imageUploadDone et window.bnineFileSelect ont ete SUPPRIMES (breaking change).
// Utilisez addEventListener('bnine:upload:done', ...) et
//          addEventListener('bnine:select:done', ...) a la place.
window.BnineModalOpen = BnineModalOpen;
window.BnineModalClose = BnineModalClose;
window.bnineSetActiveWidgetId = function (id) {
    _bnineActiveWidgetId = id;
};