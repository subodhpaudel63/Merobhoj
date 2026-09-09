<?php
declare(strict_types=1);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../helpers/audit_logger.php';
$user = require_role($conn, ['admin', 'manager']);
function po_json(array $data): never { echo json_encode($data); exit; }
function po_input(): array { $raw=file_get_contents('php://input'); $data=$_POST; if($raw){$j=json_decode($raw,true);if(is_array($j))$data=array_merge($data,$j);} return $data; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'options') {
        $sup=[]; $r=$conn->query("SELECT id,name FROM suppliers WHERE status='active' ORDER BY name"); while($r&&$x=$r->fetch_assoc())$sup[]=$x;
        $ing=[]; $r=$conn->query("SELECT id,name,unit,quantity FROM ingredients ORDER BY name"); while($r&&$x=$r->fetch_assoc())$ing[]=$x;
        po_json(['success'=>true,'suppliers'=>$sup,'ingredients'=>$ing]);
    }
    if ($action === 'view') {
        $id=(int)($_GET['id']??0); $s=$conn->prepare('SELECT p.*,s.name supplier_name FROM purchase_orders p JOIN suppliers s ON s.id=p.supplier_id WHERE p.id=?'); $s->bind_param('i',$id);$s->execute();$po=$s->get_result()->fetch_assoc();$s->close();
        $items=[];$s=$conn->prepare('SELECT i.name,i.unit,pi.quantity,pi.unit_cost,pi.total_cost FROM purchase_order_items pi JOIN ingredients i ON i.id=pi.ingredient_id WHERE pi.po_id=?');$s->bind_param('i',$id);$s->execute();$r=$s->get_result();while($x=$r->fetch_assoc())$items[]=$x;$s->close(); po_json(['success'=>true,'po'=>$po,'items'=>$items]);
    }
    $rows=[];$r=$conn->query('SELECT p.*,s.name supplier_name FROM purchase_orders p JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.created_at DESC');while($r&&$x=$r->fetch_assoc())$rows[]=$x;po_json(['success'=>true,'orders'=>$rows]);
}
$in=po_input();$token=$_SERVER['HTTP_X_CSRF_TOKEN']??($in['csrf']??null);if(!verify_panel_csrf(is_string($token)?$token:null)){http_response_code(403);po_json(['success'=>false,'message'=>'Invalid CSRF token.']);}
$action=(string)($in['action']??'');
if($action==='create'){
    $supplier=(int)($in['supplier_id']??0);$status=in_array($in['status']??'draft',['draft','ordered'],true)?$in['status']:'draft';$items=$in['items']??[];
    if(is_string($items)){$items=json_decode($items,true);}
    if($supplier<=0||!is_array($items)||count($items)===0)po_json(['success'=>false,'message'=>'Supplier and at least one item are required.']);
    foreach($items as $item){if((int)($item['ingredient_id']??0)<=0||(float)($item['quantity']??0)<=0||(float)($item['unit_cost']??0)<0)po_json(['success'=>false,'message'=>'Each item needs a valid ingredient, quantity, and cost.']);}
    $conn->begin_transaction();try{$number='PO-'.date('Ymd').'-'.str_pad((string)random_int(1,9999),4,'0',STR_PAD_LEFT);$total=0.0;foreach($items as &$item){$item['ingredient_id']=(int)($item['ingredient_id']??0);$item['quantity']=(float)($item['quantity']??0);$item['unit_cost']=(float)($item['unit_cost']??0);$item['total_cost']=round($item['quantity']*$item['unit_cost'],2);$total+=$item['total_cost'];}unset($item);
        $s=$conn->prepare('INSERT INTO purchase_orders (po_number,supplier_id,total_amount,status,notes,created_by) VALUES (?,?,?,?,?,?)');$notes=trim((string)($in['notes']??''));$s->bind_param('sidssi',$number,$supplier,$total,$status,$notes,$user['id']);$s->execute();$id=$conn->insert_id;$s->close();
        $s=$conn->prepare('INSERT INTO purchase_order_items (po_id,ingredient_id,quantity,unit_cost,total_cost) VALUES (?,?,?,?,?)');foreach($items as $item){$s->bind_param('iiddd',$id,$item['ingredient_id'],$item['quantity'],$item['unit_cost'],$item['total_cost']);$s->execute();}$s->close();$conn->commit();log_audit_action($conn,$user['id'],$user['user_type'],'CREATE_PURCHASE_ORDER','purchase_order',(int)$id,['po_number'=>$number,'total'=>$total]);po_json(['success'=>true,'id'=>$id,'po_number'=>$number]);}catch(Throwable $e){$conn->rollback();http_response_code(500);po_json(['success'=>false,'message'=>'Unable to save purchase order: '.$e->getMessage()]);}
}
if($action==='receive'){$id=(int)($in['id']??0);$conn->begin_transaction();try{$s=$conn->prepare('SELECT * FROM purchase_orders WHERE id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$po=$s->get_result()->fetch_assoc();$s->close();if(!$po||$po['status']==='received')throw new RuntimeException('Purchase order is already received or missing.');$s=$conn->prepare('SELECT ingredient_id,quantity FROM purchase_order_items WHERE po_id=?');$s->bind_param('i',$id);$s->execute();$r=$s->get_result();$u=$conn->prepare("UPDATE ingredients SET quantity=quantity+?, status=CASE WHEN quantity+?<=0 THEN 'Out' WHEN quantity+?<=low_threshold THEN 'Low' ELSE 'Available' END WHERE id=?");while($x=$r->fetch_assoc()){$q=(float)$x['quantity'];$u->bind_param('dddi',$q,$q,$q,$x['ingredient_id']);$u->execute();}$u->close();$s->close();$s=$conn->prepare("UPDATE purchase_orders SET status='received' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();$s=$conn->prepare("INSERT INTO expenses (`date`,category,title,amount,vendor,payment_method,reference_no,recorded_by) SELECT CURDATE(),'Inventory Purchase',CONCAT('Purchase order ',po_number),total_amount,s.name,'Credit',po_number,? FROM purchase_orders p JOIN suppliers s ON s.id=p.supplier_id WHERE p.id=?");$s->bind_param('ii',$user['id'],$id);$s->execute();$s->close();$conn->commit();log_audit_action($conn,$user['id'],$user['user_type'],'RECEIVE_PURCHASE_ORDER','purchase_order',$id,['po_number'=>$po['po_number'],'amount'=>$po['total_amount']]);po_json(['success'=>true]);}catch(Throwable $e){$conn->rollback();http_response_code(400);po_json(['success'=>false,'message'=>$e->getMessage()]);}}
po_json(['success'=>false,'message'=>'Unknown action.']);
