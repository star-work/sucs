<?php
namespace sucs; 
	class p_control{
		//处理登陆
		public function login_page($system){
			if(!isset($_SESSION['login'])||$_SESSION['login']===''||!isset($_POST['login'])||$_POST['login']!=$_SESSION['login']){
				$system->show_json(array('isok'=>0,'info'=>'验证码有误'));
				exit;
			}
			$s=new submit_safe_server($system);
			if($s->is_locked(20)){
				return $system->show_json(array('isok'=>0,'info'=>$system->lang('errors',100)));
			}
			$_SESSION['login']='';
			$login=new login_server($system);
			$res=$login->try_to('name',$_POST['username'],$_POST['password'],isset($_POST['remember'])&&$_POST['remember']);
			if(!$res['isok']) $s->add(4);
			$system->show_json($res);
		}
		//处理发送消息
		public function send_message_page($system){
			$login=new login_server($system);
			$uid=$login->is_login();
			if($uid!==false&&null!=$_POST['body']&&null!=$_POST['geter']){
				$geters=explode("\r\n",$_POST['geter']);
				$geters=array_unique($geters);
				$errors=array();
				$m=new message_server($system);
				$get_uid=$system->db()->prepare('SELECT `uid` FROM `users` WHERE `username`=?');
				foreach ($geters as $v){
					if($v){
						$get_uid->execute(array($v));
						if($u=$get_uid->fetchAll()){
							$m->send_message($uid,$_POST['body'],$u[0]['uid']);
						}else{
							$errors[]=$v;
						}
					}
				}
				$system->show_json(array('info'=>$errors));
			}
		}
		//文件拖拽登陆
		public function filelogin_page($system){
			if(isset($_FILES['FILE'])&&$_FILES['FILE']['error']) $system->show_json(array('error'=>$system->lang('errors',6)));
			$s=new submit_safe_server($system);
			if($s->is_locked(25)){
				echo $system->lang('errors',100);
				return;
			}
			$log=new login_server($system);
			//echo md5_file($_FILES['FILE']['tmp_name']);
			$t=$log->file_login($_FILES['FILE']['tmp_name']);
			if($t){
				$s->add(5);
				echo $system->lang('errors',7);
			}else{
				echo 'can';
			}
		}
		//处理登出
		public function logout_page($system){
			$server=new login_server($system);
			$server->logout();
			header('location: '.URLROOT.'index/login');
		}
		//处理注册
		//error说明:0表示注册成功，-1 验证码错误，1 用户名已存在
		public function reg_page($system){
			$sname=$system->ini_get('reg_ver_ses_name');
			//var_dump($_SESSION,$_POST);exit;
			if(!isset($_SESSION[$sname])||!$_SESSION[$sname]||$_POST['add']!=$_SESSION[$sname]){
				$_SESSION[$sname]='';
				$error=1;
			}else{
				$user=new user_server($system);
				$error=$user->add_user($_POST['username'],$_POST['password']);
				if($error!=0) $error+=200;
			}
			$system->show_json(array('error'=>$error,'info'=>$system->lang('errors',$error)));
		}
		//处理邀请码注册
		public function reg_for_key_page($system){
			$s=new submit_safe_server($system);
			if($s->is_locked(15)){
				return $system->show_json(array('error'=>100,'info'=>$system->lang('errors',100)));
			}
			$sname=$system->ini_get('reg_ver_ses_name');
			switch (@$_GET['stp']) {
				case '1':
						//匹配验证码
						if(@!$_SESSION[$sname]||$_POST[$sname]!=$_SESSION[$sname]){
							$_SESSION[$sname]='';
							$system->show_json(array('error'=>1,'info'=>$system->lang('errors',1)));
							exit;
						}
						//匹配邀请码
						$user=new user_server($system);
						if($res=$user->match_reg_key($_POST['key'])){
							$_SESSION[$sname.'_1']=$_SESSION[$sname];
							$_SESSION['reg_key']=$_POST['key'];
						}else{
							$s->add(6);
							$res=array('error'=>203,'info'=>$system->lang('errors',203));
						}
						$_SESSION[$sname]='';
						$system->show_json($res);
					break;
				case '2':
						//匹配记录的邀请码
						if(@!$_SESSION[$sname.'_1']||$_POST[$sname]!=$_SESSION[$sname.'_1']){
							$error=1;
							$s->add(6);
						}else{
							$user=new user_server($system);
							$error=$user->reg_key($_SESSION['reg_key'],$_POST['uid'],$_POST['username'],$_POST['password'],$_POST['group']=='auto'?'':$_POST['group']);
							if($error){
								$error+=200;
								$s->add(10);
							}
						}
						$system->show_json(array('error'=>$error,'info'=>$system->lang('errors',$error)));
					break;
			}
		}
		//处理删除消息
		public function delete_message_page($system){
			if(!isset($_POST['mid'])) $system->show_json(array('info'=>'无法取得消息id'));
			$login=new login_server($system);
			if(($uid=$login->is_login())===false) $system->show_json(array('info' =>'需要登录才有权限'));
			$_POST['mid']+=0;
			$m=new message_server($system);
			$r=$m->get_message($_POST['mid']);
			if($r&&$t=$m->is_true_message($r,$uid)) {
				$m->delete($_POST['mid'],$t);
				$system->show_json(array('info'=>''));
			}
			$system->show_json(array('info'=>'消息不存在或别人的消息'));
		}
		//处理修改密码
		public function chage_password_page($system){
			if(!isset($_POST['old_password'])||!isset($_POST['new_password'])) $system->show_json(array('info'=>'缺少必要参数'));
			$login=new login_server($system);
			if(($uid=$login->is_login())===false) $system->show_json(array('info' =>'没有发现登陆信息'));
			$res=$login->try_to('uid',$uid,$_POST['old_password']);
			if($res['isok']){
				$user=new user_server($system);
				$user->change_password($uid,$_POST['new_password']);
				$login->clean_outher_login($uid);
				$_SESSION['reset_pass_ok']=1;
				$system->show_json(array('info'=>''));
			}else{
				$system->show_json(array('info' =>'原密码错误'));
			}
		}
		//处理删除登陆文件
		public function del_loginfile_page($system){
			if(!isset($_POST['fid'])) exit;
			$login=new login_server($system);
			$login->del_loginfile($_POST['fid']);
		}
		//处理创建登陆文件
		public function create_loginfile_page($system){
			$login=new login_server($system);
			$uid=$login->is_login();
			if($uid!==null&&!$login->get_loginfile_info($uid)){
				$exc=new exc_server($system);
				if($exc->add_s($uid,"元宝",-1,"申请登陆文件成功")){
					$login->create_loginfile($uid,time()+3600*24*30);exit;
				}
				$error=1001;
			}else{
				$error=20;
			}
			$system->show_json(array('info'=>$system->lang('errors',$error)));
		}
		//处理上传头像
		public function save_head_page($system){
			$login=new login_server($system);
			$uid=$login->is_login();
			if($uid===false) $system->show_json(array('info'=>'登录信息丢失'));
			list(,$imgData)=explode(',',$_POST['img'],2);
			if(isset($imgData)){
				file_put_contents($system->ini_get('imgs_dir').'u_head/'.$uid.'.jpg',base64_decode($imgData));
				$system->show_json(array('info'=>''));
			}
			$system->show_json(array('info'=>'未发现图片'));
		}
		//处理强制下线请求
		public function kick_c_page($system){
			if(!isset($_POST['id'])) return;
			$login=new login_server($system);
			if($login->is_belong_to($_POST['id'])){
				$login->delete($_POST['id']);
			}
		}
	}