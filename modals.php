<?php
    print<<<EOF
   <!-- Modals for warning and error messages -->
    <div id="successModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><p class="text-success">Success!</p></h4>
                </div>
                <div class="modal-body">
                    <p id="successModalText" class="text-success">Something good happened.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="success-close">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="warnModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title">Oops..</h4>
                </div>
                <div class="modal-body">
                    <p id="warnModalText" class="text-warning">Giving you a warning.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="warnclose">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="errorModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title">Oops..</h4>
                </div>
                <div class="modal-body">
                    <p id="errorModalText" class="text-danger">Oh no an error occurred.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="error-close">Close</button>
                </div>
            </div>
        </div>
    </div>
EOF;
?>