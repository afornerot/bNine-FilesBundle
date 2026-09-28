//== BnineFilesBundle - Constants ===============================================
// Constantes partagees entre bninefiles.js et les templates qui
// dispatchent des CustomEvents (bnine:*).
//
// L'app hote peut importer ce fichier pour acceder aux noms d'events,
// ou simplement utiliser des chaines litterales (le format est documente
// dans le README).

(function (root) {
    'use strict';

    var BnineEvents = {
        // Modal
        MODAL_OPEN: 'bnine:modal:open',
        MODAL_CLOSE: 'bnine:modal:close',
        MODAL_CLOSED: 'bnine:modal:closed',

        // Upload / Crop
        UPLOAD_DONE: 'bnine:upload:done',
        UPLOAD_ERROR: 'bnine:upload:error',
        CROP_DONE: 'bnine:crop:done',
        CROP_ERROR: 'bnine:crop:error',

        // Selection
        SELECT_DONE: 'bnine:select:done',

        // Widget
        ICONUPLOAD_CHANGE: 'bnine:iconupload:change',
        SELECTFILE_CHANGE: 'bnine:selectfile:change',

        // Browse / Gallery
        BROWSE_DELETE: 'bnine:browse:delete',
        BROWSE_MKDIR: 'bnine:browse:mkdir',
        BROWSE_REFRESH: 'bnine:browse:refresh',
    };

    // Genere un UUID v4 (RFC 4122). Fallback si crypto.randomUUID absent.
    function uuid() {
        if (root.crypto && typeof root.crypto.randomUUID === 'function') {
            return root.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    // Helper : dispatche un CustomEvent standard (bubbles, cancelable false).
    function dispatch(name, detail, target) {
        target = target || root.document;
        var event;
        try {
            event = new CustomEvent(name, { detail: detail || {}, bubbles: true, cancelable: false });
        } catch (e) {
            // Fallback IE/old Edge
            event = root.document.createEvent('CustomEvent');
            event.initCustomEvent(name, true, false, detail || {});
        }
        target.dispatchEvent(event);
    }

    // Expose
    root.BnineEvents = BnineEvents;
    root.bnineUuid = uuid;
    root.bnineDispatch = dispatch;
})(typeof window !== 'undefined' ? window : this);