<div class="row">
    <div class="col-12 col-md-4">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title">
                    <b>
                        <?php
                        echo $this->date_start->format('d.m.Y');
                        echo ' - ';
                        echo $this->date_end->format('d.m.Y')
                        ?>
                    </b>
                </div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visits'); ?>: <b><?php echo $this->filtered_visits; ?></b></p>
                <hr class="statistics_hr-margin-small">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visitors'); ?>: <b><?php echo $this->filtered_visitors; ?></b></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_today'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visits'); ?>: <b><?php echo $this->today_visits; ?></b></p>
                <hr class="statistics_hr-margin-small">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visitors'); ?>: <b><?php echo $this->today_visitors; ?></b></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_total'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visits'); ?>: <b><?php echo $this->total_visits; ?></b></p>
                <hr class="statistics_hr-margin-small">
                <p class="h3 statistics_my-0"><?php echo $this->i18n('statistics_overview_visitors'); ?>: <b><?php echo $this->total_visitors; ?></b></p>
            </div>
        </div>
    </div>
</div>

<div class="row statistics_week_kpis">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="panel panel-default statistics-kpi-panel">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_week_visits'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><b><?php echo $this->visits_week; ?></b></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="panel panel-default statistics-kpi-panel">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_week_visitors'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><b><?php echo $this->visitors_week; ?></b></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="panel panel-default statistics-kpi-panel">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_week_top_article'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="statistics_my-0"><b><?php echo htmlspecialchars((string) $this->top_article_path_week, ENT_QUOTES); ?></b></p>
                <p class="statistics_my-0 text-muted"><?php echo $this->i18n('statistics_overview_views'); ?>: <?php echo (int) $this->top_article_count_week; ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="panel panel-default statistics-kpi-panel">
            <header class="panel-heading">
                <div class="panel-title"><b><?php echo $this->i18n('statistics_overview_week_pages_per_session'); ?></b></div>
            </header>
            <div class="panel-body">
                <p class="h3 statistics_my-0"><b><?php echo number_format((float) $this->pages_per_session_week, 2, ',', '.'); ?></b></p>
            </div>
        </div>
    </div>
</div>