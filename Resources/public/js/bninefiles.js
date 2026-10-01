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
// DEPRECATION NOTE :
// Avant, ce fichier injectait tout le wrapping HTML (preview + bouton) autour
// du champ .icon-input. C'est désormais géré côté Twig via :
//   - vendor/bnine/filesbundle/templates/Form/icon_upload.html.twig
//   - vendor/bnine/filesbundle/templates/Form/_theme.html.twig (branche icon_upload)
//   - IconUploadType::getBlockPrefix() = 'icon_upload'
//
// Le bloc ci-dessous conserve uniquement les listeners bnine:upload:done /
// bnine:crop:done qui mettent a jour la valeur du hidden. Ces listeners
// restent utiles pour les apps hotes qui n'ont pas encore migre vers
// le rendu Twig (les apps en cours de migration peuvent coexister).

$(document).ready(function () {
    // Met a jour la valeur du champ hidden apres upload ou crop.
    // On se base sur _bnineCurrentInput (positionne par BnineModalOpen).
    function applyIconUploadResult(detail) {
        var currentInputId = _bnineCurrentInput;
        if (!currentInputId) return;

        var input = document.getElementById(currentInputId);
        if (!input) return;

        var filepath = (detail && detail.path) || '';

        input.value = filepath;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('bnine:upload:done', function (e) {
        applyIconUploadResult(e.detail || {});
    });
    document.addEventListener('bnine:crop:done', function (e) {
        applyIconUploadResult(e.detail || {});
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