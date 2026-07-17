<?php
$title = (string) $this->getVar('title');
$chart = (string) $this->getVar('chart');
$table = (string) $this->getVar('table');
$note = (string) $this->getVar('note', '');
$modalId = (string) $this->getVar('modalid', '');
?>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title" style="display:flex; align-items:center;">
                    <b><?php echo htmlspecialchars($title, ENT_QUOTES); ?></b>
                    <?php if ('' !== $note && '' !== $modalId) : ?>
                        <button type="button" class="btn btn-link" data-toggle="modal" data-target="#<?= htmlspecialchars($modalId, ENT_QUOTES); ?>">
                            <i class="rex-icon fa-fw rex-icon-info"></i>
                        </button>
                    <?php endif ?>
                </div>
            </header>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-12 col-lg-6">
                        <?php echo $chart; ?>
                    </div>
                    <div class="col-sm-12 col-lg-6">
                        <?php echo $table; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ('' !== $note && '' !== $modalId) : ?>

    <div class="modal fade" id="<?= htmlspecialchars($modalId, ENT_QUOTES); ?>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><b><?= htmlspecialchars($title, ENT_QUOTES); ?></b></h4>
                </div>
                <div class="modal-body">
                    <?= $note; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

<?php endif ?>