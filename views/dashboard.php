<div class="page-heading"><div><p class="eyebrow">STAFF CRM</p><h1><?=e(t('welcome'))?></h1></div>
<form method="get" class="period-form"><input type="hidden" name="route" value="dashboard"><input type="hidden" name="tab" value="<?=e($tab)?>"><label for="period"><?=e(t('period'))?></label><select id="period" name="period" class="form-control" <?=$periods?'':'disabled'?>>
<?php if(!$periods): ?><option><?=e(t('all_periods'))?></option><?php endif; ?>
<?php foreach($periods as $p): ?><option value="<?=e($p)?>" <?=$p===$period?'selected':''?>><?=e($p)?></option><?php endforeach; ?>
</select><?php if($periods): ?><button class="btn btn-primary"><?=e(t('apply'))?></button><?php endif; ?></form></div>
<nav class="tab-nav" aria-label="Dashboard"><a data-tab="network" class="<?=$tab==='network'?'active':''?>" href="<?=e(routeUrl('dashboard',['tab'=>'network','period'=>$period]))?>"><?=e(t('network'))?></a><a data-tab="statistics" class="<?=$tab==='statistics'?'active':''?>" href="<?=e(routeUrl('dashboard',['tab'=>'statistics','period'=>$period]))?>"><?=e(t('statistics'))?></a></nav>
<section id="network-panel" class="tab-panel" <?=$tab==='network'?'':'hidden'?>>
 <div class="coverage"><span><?=e(t('period'))?>: <strong><?=e($period??'—')?></strong></span><span><?=e(t('coverage'))?>: <strong><?=count(array_filter($rows,function($row){return $row['collected_at']!==null;}))?> / <?=count($rows)?></strong></span></div>
 <div class="metric-grid"><article class="metric-card"><span class="metric-label"><?=e(t('crm_total'))?></span><strong><?=count($rows)?></strong><span class="metric-note">STAFF CRM</span></article>
 <?php foreach($totals as $metric=>$sum): ?><article class="metric-card"><span class="metric-label"><?=e(t($metric))?></span><strong><?=numberValue($sum['value'])?></strong><span class="metric-note"><?=$sum['value']===null?e(t('no_data')):(!$sum['complete']?e(t('partial')).' · '.$sum['known'].' / '.count($rows):e($period??''))?></span></article><?php endforeach; ?></div>
 <section class="panel"><div class="panel-heading"><h2><?=e(t('map'))?></h2><span class="muted"><?=e(t('no_location'))?>: <?=count(array_filter($rows,function($row){return Statistics::coordinates($row)===null;}))?></span></div>
 <div id="network-map" class="map" data-map-url="<?=e(routeUrl('map',['period'=>$period]))?>" aria-label="<?=e(t('map'))?>"></div><div class="map-error" role="status" hidden></div>
 <?php if(!$rows): ?><p class="empty-state"><?=e(t('empty'))?></p><?php endif; ?>
 <?php $unknown=array_filter($rows,function($row){return Statistics::coordinates($row)===null;}); if($unknown): ?><div class="unknown-list"><strong><?=e(t('no_location'))?></strong><?php foreach($unknown as $row): ?><a href="<?=e(routeUrl('installation',['uuid'=>$row['uuid']]))?>"><?=e($row['name'])?></a><?php endforeach; ?></div><?php endif; ?>
 </section>
</section>
<section id="statistics-panel" class="tab-panel" <?=$tab==='statistics'?'':'hidden'?>>
<section class="panel"><div class="panel-heading"><h2><?=e(t('statistics'))?></h2><span class="muted"><?=e($period??t('all_periods'))?></span></div>
<div class="table-wrap"><table id="crm-table" class="table table-hover"><thead><tr><?php foreach(['church','country','postcode','registered','last_collection','status','members_total'] as $label): ?><th><?=e(t($label))?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><a class="church-link" href="<?=e(routeUrl('installation',['uuid'=>$row['uuid']]))?>"><?=e($row['name'])?></a></td><td><?=e($row['country_iso']??'—')?></td><td><?=e($row['postcode']??'—')?></td><td><?=e($row['registered_at'])?></td><td><?=e($row['last_collected_at']??'—')?></td><td><?php $state=$row['latest_success']!==null && (int)$row['latest_success']===0?'failed':($row['collected_at']!==null?'received':'missing'); ?><span class="state <?=$state?>"><?=e(t($state))?></span></td><td data-order="<?=e($row['members_total']??-1)?>"><?=numberValue($row['members_total'])?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="small muted"><?=e(t('utc'))?></p>
</section></section>
