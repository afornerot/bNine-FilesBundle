//== BnineFilesBundle - Icon Upload Widget =====================================
function BnineModalLoad(idmodal, title, path) {
    var modal = document.getElementById(idmodal);
    if (!modal) {
        modal = document.createElement('div');
        modal.id = idmodal;
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.innerHTML =
            '<div class="modal-dialog modal-lg">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h4 class="modal-title"></h4>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<iframe id="framemodal" frameborder="0" width="100%" height="600px"></iframe>' +
            '</div>' +
            '</div>' +
            '</div>';
        document.body.appendChild(modal);
    }
    modal.querySelector('.modal-header h4').textContent = title;
    modal.querySelector('#framemodal').src = path;

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

$(document).ready(function () {
    $('.icon-input').each(function () {
        var $input = $(this);
        var $id = $input.attr('id') || 'id';
        var value = $input.val() || '';
        var endpoint = $input.data('icon-endpoint') || 'icon';
        var label = $input.data('icon-label') || 'Icon';
        var uploadUrl = $input.data('upload-url') || '';

        if ($input.parent().hasClass('icon-wrapper')) {
            return;
        }

        var $wrapper = $('<div class="text-center d-flex flex-column align-items-center mb-3 icon-wrapper"></div>');
        $input.wrap($wrapper);

        var $preview = $('<img id="' + $id + '_img" class="bigavatar mb-2" style="background-color: var(--bs-dark);">');
        $preview.attr('src', value ? '/' + value : '');
        if (!value) {
            $preview.css('display', 'none');
        }
        $input.parent().prepend($preview);

        if (uploadUrl) {
            var $btn = $('<a class="btn btn-info" style="max-width:100%; margin-bottom:15px;" data-bs-toggle="modal" data-bs-target="#mymodal"></a>');
            $btn.attr('onclick', "BnineModalLoad('mymodal','" + label + "','" + uploadUrl + "');");
            $btn.attr('title', 'Ajouter ' + label);
            $btn.text('Modifier');
            $input.parent().append($btn);
        }

        $input.on('change', function () {
            var val = $(this).val();
            if (val) {
                $preview.attr('src', '/' + val).show();
            } else {
                $preview.hide();
            }
        });
    });
});

window.imageUploadDone = function (filepath) {
    var modals = document.querySelectorAll('.modal.show');
    for (var i = 0; i < modals.length; i++) {
        var bsModal = bootstrap.Modal.getInstance(modals[i]);
        if (bsModal) bsModal.hide();
    }
};
