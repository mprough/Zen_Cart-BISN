<?php
if (class_exists('ProWebsBisn', false)
    && ProWebsBisn::enabled()
    && !empty($_SESSION['customer_id'])) {
    ?>
    <p>
        <a href="<?= ProWebsBisn::h(zen_href_link(
            FILENAME_ACCOUNT_BACK_IN_STOCK_NOTIFICATIONS,
            '',
            'SSL'
        )) ?>">
            <?= ProWebsBisn::h(BISN_ACCOUNT) ?>
        </a>
    </p>
    <?php
}
?>
