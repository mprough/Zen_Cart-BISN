<?php
if (class_exists('ProWebsBisn', false) && ProWebsBisn::enabled()) {
    $bisnProductId = (int)ProWebsBisn::scalar($_GET, 'products_id');
    $bisnProduct = ProWebsBisn::product(
        $bisnProductId,
        (int)($_SESSION['languages_id'] ?? 1)
    );
    if ($bisnProduct && (float)$bisnProduct['products_quantity'] <= 0) {
        $bisnUrl = zen_href_link(
            FILENAME_BACK_IN_STOCK_NOTIFICATION_SUBSCRIBE,
            'products_id=' . $bisnProductId,
            'SSL'
        );
        ?>
        <div class="bisn-notice">
            <p>
                <a class="btn btn-info" href="<?= ProWebsBisn::h($bisnUrl) ?>">
                    <?= ProWebsBisn::h(BISN_REQUEST) ?>
                </a>
            </p>
        </div>
        <?php
    }
    unset($bisnProductId, $bisnProduct, $bisnUrl);
}
?>
