<?php

$cards = [
    [
        'title' => $this->i18n('statistics_analysis_card_device_title'),
        'description' => $this->i18n('statistics_analysis_card_device_desc'),
        'icon' => 'fa-mobile',
        'target' => 'statistics_lazy_device',
    ],
    [
        'title' => $this->i18n('statistics_analysis_card_extended_title'),
        'description' => $this->i18n('statistics_analysis_card_extended_desc'),
        'icon' => 'fa-line-chart',
        'target' => 'statistics_lazy_extended',
    ],
    [
        'title' => $this->i18n('statistics_analysis_card_bots_title'),
        'description' => $this->i18n('statistics_analysis_card_bots_desc'),
        'icon' => 'fa-bug',
        'target' => 'statistics_lazy_bots',
    ],
];

?>

<div class="row statistics_cards_row">
    <div class="col-12">
        <h4 class="statistics_cards_heading"><?php echo $this->i18n('statistics_analysis_heading'); ?></h4>
    </div>
    <?php foreach ($cards as $card) { ?>
        <div class="col-sm-4">
            <button
                type="button"
                class="statistics-analysis-card"
                data-statistics-focus-lazy="<?php echo htmlspecialchars($card['target'], ENT_QUOTES); ?>"
                data-date-start="<?php echo htmlspecialchars((string) $this->getVar('date_start', ''), ENT_QUOTES); ?>"
                data-date-end="<?php echo htmlspecialchars((string) $this->getVar('date_end', ''), ENT_QUOTES); ?>"
            >
                <span class="statistics-analysis-card__icon"><i class="fa <?php echo htmlspecialchars($card['icon'], ENT_QUOTES); ?>" aria-hidden="true"></i></span>
                <span class="statistics-analysis-card__content">
                    <span class="statistics-analysis-card__title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES); ?></span>
                    <span class="statistics-analysis-card__desc"><?php echo htmlspecialchars($card['description'], ENT_QUOTES); ?></span>
                </span>
            </button>
        </div>
    <?php } ?>
</div>
