<?php
/** Store demo requests independently from CRM installations and statistics. */
final class Orders
{
    const STATUSES=['waiting','issued','paid','installed'];
    private $db;
    // Keep database access within the authenticated admin/import boundaries.
    public function __construct(PDO $db){$this->db=$db;}
    // Validate explicit form fields without coercing phone numbers or member ranges.
    public static function validate(array $input): array
    {
        $row=[];
        foreach(['contact'=>200,'email'=>254,'phone'=>80,'city'=>200,'church'=>200,'members'=>80,'message'=>10000,'ip'=>45,'source_ref'=>128,'order_date'=>10] as $field=>$max){
            $value=$input[$field]??'';if(!is_string($value)||mb_strlen($value)>$max||strpos($value,"\0")!==false)throw new InvalidArgumentException('Invalid order');$row[$field]=trim($value);
        }
        if($row['contact']===''||!filter_var($row['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid order');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$row['order_date']);if(!$date||$date->format('Y-m-d')!==$row['order_date'])throw new InvalidArgumentException('Invalid order date');
        if($row['ip']!==''&&!filter_var($row['ip'],FILTER_VALIDATE_IP))throw new InvalidArgumentException('Invalid IP');
        if($row['source_ref']!==''&&!preg_match('/^[a-zA-Z0-9_.:-]+$/D',$row['source_ref']))throw new InvalidArgumentException('Invalid source reference');
        return $row;
    }
    // Accept only the four workflow states and bounded plain-text notes.
    public static function validateUpdate($status,$comment): void
    {
        if(!is_string($status)||!in_array($status,self::STATUSES,true)||!is_string($comment)||mb_strlen($comment)>10000||strpos($comment,"\0")!==false)throw new InvalidArgumentException('Invalid order update');
    }
    // Return newest requests first for the searchable admin table.
    public function all(): array{return $this->db->query('SELECT * FROM demo_orders ORDER BY order_date DESC,id DESC')->fetchAll();}
    // Deduplicate webhook retries without overwriting an administrator's status or notes.
    public function create(array $input): int
    {
        $r=self::validate($input);$columns=array_keys($r);$values=array_values($r);$values[array_search('source_ref',$columns)]=$r['source_ref']===''?null:$r['source_ref'];
        $q=$this->db->prepare('INSERT INTO demo_orders('.implode(',',$columns).',comment,created_at,updated_at) VALUES('.implode(',',array_fill(0,count($columns),'?')).',\'\',UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$q->execute($values);return (int)$this->db->lastInsertId();
    }
    // Save status and comment together; reject stale forms instead of losing newer notes.
    public function update(int $id,int $version,string $status,string $comment): bool
    {
        self::validateUpdate($status,$comment);$q=$this->db->prepare('UPDATE demo_orders SET status=?,comment=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=? AND version=?');$q->execute([$status,$comment,$id,$version]);return $q->rowCount()===1;
    }
}
