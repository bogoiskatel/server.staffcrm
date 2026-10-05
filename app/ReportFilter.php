<?php
/** Validated report selection shared by registry, dashboard and map requests. */
final class ReportFilter
{
    public $mode,$period,$year,$dateStart,$dateEnd,$endExclusive,$params;
    // Preserve old period links while accepting explicit all/year/month/date-range reports.
    public static function fromQuery(array $query,?string $defaultPeriod,DateTimeImmutable $now=null): self
    {
        $now=($now?:new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Europe/Kiev'));$f=new self();
        $f->mode=$query['report']??'month';
        if(!is_string($f->mode)||!in_array($f->mode,['all','year','month','range'],true))throw new InvalidArgumentException('Invalid report mode');
        $f->period=$query['period']??($defaultPeriod?:$now->format('Y-m'));
        if(!is_string($f->period))throw new InvalidArgumentException('Invalid period');
        $current=$f->period==='current';if($current)$f->period=$now->format('Y-m');
        if(!Statistics::validPeriod($f->period))throw new InvalidArgumentException('Invalid month');
        $f->year=$query['year']??$now->format('Y');
        if(!is_string($f->year)||!preg_match('/^[1-9][0-9]{3}$/D',$f->year))throw new InvalidArgumentException('Invalid year');
        $f->dateStart=$query['date_start']??$now->format('Y-m-01');$f->dateEnd=$query['date_end']??$now->format('Y-m-d');
        foreach([$f->dateStart,$f->dateEnd] as $date){if(!is_string($date))throw new InvalidArgumentException('Invalid date');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$date||!Statistics::validPeriod(substr($date,0,7)))throw new InvalidArgumentException('Invalid date');}
        if($f->dateStart>$f->dateEnd)throw new InvalidArgumentException('Reversed date range');
        $f->endExclusive=(new DateTimeImmutable($f->dateEnd,new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s');
        $f->params=['report'=>$f->mode];
        if($f->mode==='year')$f->params['year']=$f->year;
        elseif($f->mode==='month')$f->params['period']=$current?'current':$f->period;
        elseif($f->mode==='range'){$f->params['date_start']=$f->dateStart;$f->params['date_end']=$f->dateEnd;}
        return $f;
    }
    // Keep report captions localized while exposing their exact selected boundaries.
    public function label(): string
    {
        if($this->mode==='all')return t('report_all');
        if($this->mode==='year')return t('report_year').' '.$this->year;
        if($this->mode==='range')return $this->dateStart.' — '.$this->dateEnd.' (UTC)';
        return $this->period;
    }
}
