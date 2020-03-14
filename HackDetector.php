<?php

/**
 * @name HackDetector
 * @main Securti\hackdetector\HackDetector
 * @author ["Flug-in-Fabrik", "Securti"]
 * @version 0.1
 * @api 3.10.0
 * @description 여러 종류의 핵을 감지합니다
 * 해당 플러그인 (HackDetector)은 Fabrik-EULA에 의해 보호됩니다
 * Fabrik-EULA : https://github.com/Flug-in-Fabrik/Fabrik-EULA
 */
 
namespace Securti\hackdetector;
 
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\entity\Entity;
use pocketmine\entity\Creature;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;

use pocketmine\utils\Internet;

class HackDetector extends PluginBase implements Listener{
   
  public $data; //플레이어 데이터
  
  private $message_prefix = "[prefix] "; //접두사
  private $message_base = "null"; //출력 메세지 ({prefix} - 접두사, {name} - 플레이어 이름, {time} - 처리 일자(Y년 M월 D일 h시 m분 s초), {cause} - 사유, {process_type} - 처리 타입, {n} - 줄바꿈)
  
  private $acces_token = "null"; //토큰
  private $band_key = "null"; //밴드 키
  private $band_message = "null"; //밴드 메세지 ({name} - 플레이어 이름, {time} - 처리 일자(Y년 M월 D일 h시 m분 s초), {cause} - 사유, {process_type} - 처리 타입, {n} - 줄바꿈)
  private $band_mode = "disable"; //밴드 사용여부
  
  private $bulldozer = 0; //불도저 블럭 파괴 수 (1초간 파괴되는 수가 해당 값 이상일 경우 작동됩니다)
  private $type_bulldozer = "null"; //불도저 처리 타입
  private $mode_bulldozer = "disable"; //불도저 사용 여부
  
  private $chatplaster = 0; //채팅 도배 메세지 수 (같은 메세지를 해당 값 이상으로 입력 할 경우 작동됩니다)
  private $type_chatplaster = "null"; //채팅 도배 처리 타입
  private $mode_chatplaster = "disable"; //채팅 도배 사용 여부
  
  private $reach = 0; //리치핵 거리 (화살, 눈덩이, 포션...등의 Projectile이 아닌 무기에 의해 맞은 거리가 해당 값 이상일 경우 작동됩니다)
  private $type_reach = "null"; //리치핵 처리 타입
  private $mode_reach = "disable"; //리치핵 사용 여부
  
  private $destroy = [0]; //부적절 블럭 파괴 ([아이디:데미지, 아이디:데미지]로 작성하여 해당 블럭 중 하나라도 op가 아닌 플레이어가 파괴할 시 작동됩니다)
  private $type_destroy = "null"; //부적절 블럭 파괴 타입
  private $mode_destroy = "disable"; //부적절 블럭 파괴 사용 여부
  
  /*
  밴드 API를 사용하여 게시글을 올릴 여부를 선택 할 수 있습니다 (DPMMP와 윈도우의 환경에서는 작동하지 않을 수 있습니다)
  
  특정의 값이 설정된 값 이상일 경우 밴 또는 킥 등의 시스템이 작동됩니다
  type에서 밴은 ban, 킥은 kick으로 설정 할 수 있습니다 (null이나 그 이외의 경우 kick으로 처리됩니다)
  
  mode에서는 해당 기능의 사용 여부를 결정 할 수 있습니다
  enable(활성화), disable(비활성화)가 존재합니다 (null이나 그 이외의 경우 disable로 처리됩니다)
  */
  
  public function onEnable(){
  
    $this->getServer()->getPluginManager()->registerEvents($this,$this);
  }
  public function onJoin(PlayerJoinEvent $e){
  
    $player = $e->getPlayer();
    $name = strtolower($player->getName());
    
    if(!isset($this->data[$name]["bulldozer"])){
    
      $this->data[$name]["bulldozer"] = 0;
      $this->data[$name]["bulldozer_time"] = time();
    }
    
    if(!isset($this->data[$name]["chatplaster"])){
    
      $this->data[$name]["chatplaster"] = 0;
      $this->data[$name]["chatplaster_message"] = "";
    }
  }
  public function onBreak(BlockBreakEvent $e){
  
    $player = $e->getPlayer();
    $name = strtolower($player->getName());
    
    if(isset($this->data[$name]["bulldozer"])){
    
      if($this->data[$name]["bulldozer_time"] !== time()){
      
        $this->data[$name]["bulldozer_time"] = time();
        $this->data[$name]["bulldozer"] = 1;
      }
      else{
      
        $this->data[$name]["bulldozer_time"] = time();
        $this->data[$name]["bulldozer"] = $this->data[$name]["bulldozer"] + 1;
        
        if($this->mode_bulldozer === "enable"){
        
          if($this->data[$name]["bulldozer"] >= $this->bulldozer){
          
            if($this->type_bulldozer === "ban"){
            
              $this->getServer()->getNameBans()->addBan($name, "불도저");
              $player->kick("불도저를 사용하여 밴이 되었습니다");
              
              $this->Message($name, "불도저", "밴");
            }
            else{
            
              $player->kick("불도저를 사용하여 킥이 되었습니다");
              
              $this->Message($name, "불도저", "킥");
            }
          }
        }
      }
    }
    
    $block = $e->getBlock();
    
    $id = $block->getId();
    $dm = $block->getDamage();
    $meta = $id.":".$dm;
    
    if(in_array($meta, $this->destroy)){
    
      if($this->mode_destroy === "enable"){
      
        if($this->type_destroy === "ban"){
        
          $this->getServer()->getNameBans()->addBan($name, "부적절한 블럭 파괴");
          $player->kick("부적절한 블럭 파괴를 하여 밴이 되었습니다");
          
          $this->Message($name, "부적절한 블럭파괴", "밴");
        }
        else{
            
          $player->kick("부적절한 블럭 파괴를 하여 킥이 되었습니다");
          
          $this->Message($name, "부적절한 블럭파괴", "킥");
        }
      }
    }
  }
  public function onDamage(EntityDamageEvent $e){
  
    if($e instanceof EntityDamageByEntityEvent){
    
      $damager = $e->getDamager();
      $name = strtolower($damager->getName());
      
      $entity = $e->getEntity();
      
      if($damager instanceof Player){
      
        if($e->getCause() !== EntityDamageByEntityEvent::CAUSE_PROJECTILE){
        
          if($damager->distance($entity) >= $this->reach){
          
            if($this->mode_reach === "enable"){
            
              if($this->type_reach === "ban"){
              
                $this->getServer()->getNameBans()->addBan($name, "리치핵");
                $player->kick("리치핵을 사용하여 밴이 되었습니다");
                
                $this->Message($name, "리치핵", "밴");
              }
              else{
                  
                $player->kick("리치핵을 사용하여 킥이 되었습니다");
                
                $this->Message($name, "리치핵", "킥");
              }
            }
          }
        }
      }
    }
  }
  public function onChat(PlayerChatEvent $e){
  
    $player = $e->getPlayer();
    $name = strtolower($player->getName());
    
    if(isset($this->data[$name]["chatplaster"])){
    
      if($this->data[$name]["chatplaster_message"] === $e->getMessage()){
      
        $this->data[$name]["chatplaster_message"] = $e->getMessage();
        $this->data[$name]["chatplaster"] = $this->data[$name]["chatplaster"] + 1;
        
        if($this->mode_chatplaster === "enable"){
            
          if($this->type_chatplaster === "ban"){
          
            $this->getServer()->getNameBans()->addBan($name, "채팅 도배");
            $player->kick("채팅 도배를 하여 밴이 되었습니다");
            
            $this->Message($name, "채팅 도배", "밴");
          }
          else{
              
            $player->kick("채팅 도배를 하여 킥이 되었습니다");
            
            $this->Message($name, "채팅 도배", "킥");
          }
        }
      }
      else{
      
        $this->data[$name]["chatplaster_message"] = $e->getMessage();
      }
    }
  }
  public function Message($name, $cause, $process_type){
  
    $message = $this->message_base;
    $message = str_replace("{prefix}", $this->message_prefix, $message);
    $message = str_replace("{name}", $name, $message);
    $message = str_replace("{time}", date("Y년 n월 j일 H시 i분 s초"), $message);
    $message = str_replace("{cause}", $cause, $message);
    $message = str_replace("{process_type}", $process_type, $message);
    $message = str_replace("{n}", "\n", $message);
    
    $this->getServer()->broadCastMessage($message);
    
    if($this->band_mode === "enable"){
    
      $content = $this->band_message;
      $content = str_replace("{name}", $name, $content);
      $content = str_replace("{time}", date("Y년 n월 j일 H시 i분 s초"), $content);
      $content = str_replace("{cause}", $cause, $content);
      $content = str_replace("{process_type}", $process_type, $content);
      $content = str_replace("{n}", "\n", $content);
      
      Internet::postURL("https://openapi.band.us/v2.2/band/post/create?access_token=".$acces_token, ["band_key" => $band_key, "content" => $content]);
    }
  }
} 
