<?php
date_default_timezone_set('Asia/Calcutta');
	ini_set('display_errors',0);
	require_once('Smarty.class.php');
	$tpl=new Smarty();
	session_start();
	if(isset($_SESSION['uid']))
	{
		require_once('get_services.php');
		$obj=new get_services($_SESSION['uid']);
		require_once('connection.php');
		$conn=getconnection();	

		
			
			

		$sql="select person_name,email,hno,address,ward_id,street_id,mobile,cat3_id,comp_desc,grievance_origin_id,grievance_status_id,date_regd,file_no from grievances where grievance_id='".$_POST['grievance_id']."' and ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			$field_info = mysqli_fetch_fields($rs);
			$row = mysqli_fetch_assoc($rs);
			foreach($field_info as $fi => $f) 
				$data1[$f->name]=$row[$f->name];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));
			
			
		$sql ="select * from category3_mst where ulbid='".$_SESSION['ulbid']."'";
		$rs = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_assoc($rs))
		{
		$cat3_list[$row['cs_id']]=$row['comp_desc'];
		}

		$sql="select emp_id,emp_name,emp_dept,emp_desg,emp_mobile from emp_mst where ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
			{
				$emp_list[$row['emp_id']]['emp_name']=$row['emp_name'];
				$emp_list[$row['emp_id']]['emp_dept']=$row['emp_dept'];
				$emp_list[$row['emp_id']]['emp_desg']=$row['emp_desg'];
				$emp_list[$row['emp_id']]['emp_mobile']=$row['emp_mobile'];
			}
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));


		if(isset($_POST['save']))
		{
			
			require_once('get_ulb_info.php');
			$ulb_info = get_ulb_info();
			
			$sql ="select * from grievance_status_mst";
			
			if($rs=mysqli_query($conn,$sql))
    		{
    			while($row = mysqli_fetch_assoc($rs))
    				$grievance_status_list[$row['grievance_status_id']]=$row['grievance_status_desc'];
    		}
    		else
    			printf("Errormessage: %s\n", mysqli_error($conn));
				
			if($_POST['disposal_status']==9)
			{
			$string="Completed";
			$_POST['disposal_status']=3;
			}
			else
			{
			$string=$grievance_status_list[$_POST['disposal_status']];
			}
			
			if($_POST['disposed_date']=='')
			{
			    $tpl->assign('msg','Select Disposable date');
			}
			else
			{
			$curtime = date('H:i:s');
			
			$_POST['disposed_date']=$_POST['disposed_date']." ".$curtime;
			
			 $sql="update grievances_transactions set disposal_status=".$_POST['disposal_status'].",disposal_remarks='".mysqli_real_escape_string($conn,$_POST['disposal_remarks'])."',disposed_date='".date('Y-m-d H:i:s',strtotime($_POST['disposed_date']))."',updated_by='".$_SESSION['uid']."',origin_id='3',rca='".mysqli_real_escape_string($conn,$_POST['rca'])."',ca='".mysqli_real_escape_string($conn,$_POST['ca'])."' where  grievance_id=".$_POST['grievance_id']." and transaction_id=".$_POST['transaction_id'];
			echo $sql;
			if(mysqli_query($conn,$sql))
			{
			    
			    // calling api
			    
			    if($_POST['disposal_status']=='3' || $_POST['disposal_status']=='4' || $_POST['disposal_status']=='6' || $_POST['disposal_status']=='8')
			    {
			        $status_id=4;
			    }
			    else if($_POST['disposal_status']=='10')
			    {
			        
			        $status_id=6;
			    }
			    
			    $sql ="select u.api_ulbname,u.ulbname,g.app_type_id,c.swatchta_app_status_yn,c.swapp_cat_id,g.generic_id from ulbmst u, grievances g,cs_mst c where g.ulbid=u.ulbid and g.cat3_id=c.cs_id and g.grievance_id='".$_POST['grievance_id']."'";
		    $rs =mysqli_query($conn,$sql);
		    $row = mysqli_fetch_assoc($rs);
		    $vendor_name=$row['api_ulbname'];
		    $app_type_id1=$row['app_type_id'];
		    $swatchta_app_status_yn=$row['swatchta_app_status_yn'];
		    $swapp_cat_id=$row['swapp_cat_id'];
		    $generic_id=$row['generic_id'];
		    //$complaint_id=substr($row['generic_id'],-7);
		     $newarray=explode('C',$row['generic_id']);
            
            $complaint_id=$newarray[1];
		    
		    
			    if($app_type_id1=='1')
				{
				    
				    if($swatchta_app_status_yn=='1')
				    {
			    
                			    if($status_id==4 || $status_id==6)
                			    {
                			        
                			        if($_POST['disposal_remarks']=='')
                			        {
                			            $_POST['disposal_remarks']='comment not given';
                			        }
                			        
                			        $ch = curl_init();
                                    $data = array(
                                        'statusId'=>$status_id,
                                        'complaintId'=>$complaint_id,
                                        'commentDescription'=>$_POST['disposal_remarks'],
                                        'deviceOs'=>'external',
                                        'vendor_name' => $vendor_name,
                                        'access_key' => $_SESSION['access_key'],
                                        'apiKey'=>'af4e61d75d2782a33eac7641e42bba6f'
                                        );
                                        
                                        
                                    curl_setopt($ch, CURLOPT_URL, 'http://api.swachh.city/engineer/v1/complaint-status-update');
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // required as of PHP 5.6.0
                                    curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $output=curl_exec($ch);
                                    
                                    $arr=json_decode($output,TRUE);
                                    $sql ="update grievances_transactions set http_code='".$arr['httpCode']."',code='".$arr['code']."',id='".$arr['complaint']['id']."' where grievance_id='".$_POST['grievance_id']."'";
                                    mysqli_query($conn,$sql);
                                    
                                    
                                    if($arr['httpCode']==200 && $arr['code']==2000)
                                        {
                                            
                                            $sql="insert into swatchata_comp_status_map(generic_id,status_id,complaint_id)values('".$generic_id."','".$status_id."','".$arr['complaint']['id']."')";
                                            mysqli_query($conn,$sql);
                                            $sql ="update grievances set swatchta_app_status='".$status_id."' where grievance_id='".$_POST['grievance_id']."'";
                                            mysqli_query($conn,$sql);
                                        }
                			    
                			    
                			    }
			    
				    }
				}
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			    
			
				$sql ="select ulbname,ulb_type_desc from ulbmst u,grievances g,ulb_type ut where g.ulbid=u.ulbid and u.ulb_type=ut.ulb_type_id and g.grievance_id='".$_POST['grievance_id']."'";
				
				$rs = mysqli_query($conn,$sql);
				$row = mysqli_fetch_assoc($rs);
				$ulbname=$row['ulbname']." ".$row['ulb_type_desc'];
			
				$sql ="select emp_mobile,emp_name from emp_mst e,grievances_transactions gt where gt.emp_id=e.emp_id and gt.grievance_id='".$_POST['grievance_id']."'";
				$rs = mysqli_query($conn,$sql);
				$row_emp=mysqli_fetch_assoc($rs);
				$emp_mobile=$row_emp['emp_mobile'];
				$emp_name=$row_emp['emp_name'];
				
				
			if($_SESSION['ulbid']==207 || $_SESSION['ulbid']==500)
					{
					    
					    if($_POST['app_type_id']=='1')
					    {
					    $file_no=$_POST['grievance_id'];
					    }
					    else
					    {
					//$grievance_id2=$grievance_id;
					$file_no=$_POST['file_no'];
					    }
					    
					    
					    
					    
				//	$file_no=$_POST['file_no'];
					}
					else
					{
					$file_no=$_POST['grievance_id'];
					}
				$tpl->assign('class','alert alert-success display-hide');
				$msg="Successfully Updated  Details";
				if($_POST['disposal_status']==5)
				{
					
					$sms1="Dear ".$data1['person_name'].", Your Service  regarding ".$cat3_list[$data1['cat3_id']]." with Ref No : ".$file_no." was Transferred to ".$emp_list[$_POST['emp_id']]['emp_name']." on ".$_POST['disposed_date']." Regards - Grievance Monitoring Cell , ".$ulbname;
		
					$sms2="Dear ".$emp_list[$_POST['emp_id']]['emp_name'].", A Service by ".$data1['person_name']."  regarding ".$cat3_list[$data1['cat3_id']]." with Ref No : ".$file_no." was allotted to you on ".$_POST['disposed_date']." Regards - Citizen Service Monitoring Cell , ".$ulbname;					

					$message="Dear ".$data1['person_name'].",\n\nYour Service  regarding ".$cat3_list[$data1['cat3_id']]." with Ref No : ".$_POST['grievance_id']." was Transferred to ".$emp_list[$_POST['emp_id']]['emp_name']." on ".$_POST['disposed_date'].". You can check the status of the Service any time by using the link ".$ulb_info['url']."\n\nRegards,\n\nCitizen Service Redressal Team,\n".$ulbname;

					$transaction_id=$_POST['transaction_id']+1;
					 $sql1="insert into grievances_transactions(grievance_id,transaction_id,emp_id,alloted_date,dept_id) values('".$_POST['grievance_id']."','".$transaction_id."','".$_POST['emp_id']."','".date('Y-m-d H:M:S',strtotime($_POST['disposed_date']))."','".$_POST['emp_dept']."')";
					 
    					$sql_emp ="select emp_mobile from emp_mst e,grievances_transactions gt where gt.emp_id=e.emp_id and gt.grievance_id='".$_POST['grievance_id']."' and disposal_status='2'";
        				$rs_emp = mysqli_query($conn,$sql_emp);
        				$row_emp=mysqli_fetch_assoc($rs_emp);
        				
        				$emp_mobile=$row_emp['emp_mobile'];
				}
				else
				{
					
					if($_POST['app_type_id']=='1')
						{
						$sql ="select cs_id,cs_desc as comp_desc from cs_mst";
						}
						else
						{
						$sql ="select cs_id,comp_desc from category3_mst where ulbid='".$_SESSION['ulbid']."'";
						}
						$rs = mysqli_query($conn,$sql);
						while($row = mysqli_fetch_assoc($rs))
						{
						$cs_list[$row['cs_id']]=$row['comp_desc'];
						}
						
						
						
						// set here municipality name
						
						
						
						
						
					$sms1="Dear ".$data1['person_name'].", Your Service  regarding ".$cs_list[$data1['cat3_id']]." with Ref No : ".$file_no." Status is ".$string." , ".$_POST['disposal_remarks']." Regards - Citizen Service Monitoring Cell , ".$ulbname;
					
					$sms2="Dear ".$emp_name.", A Service  Updated to  ".$string." , regarding ".$cs_list[$data1['cat3_id']]." with Ref No : ".$file_no;	

					$message="Dear ".$data1['person_name'].",\n\nYour Service  regarding ".$cs_list[$data1['cat3_id']]." with Ref No : ".$file_no." Status is ".$string.". You can check the status of the Service any time by using the link ".$ulb_info['url']."\n\nRegards,\n\nCitizen Service Redressal Team,\n".$ulbname;

					$sql1="update grievances set grievance_status_id=".$_POST['disposal_status'].",updated_by='".$_SESSION['uid']."' where  grievance_id=".$_POST['grievance_id'];
				}
				mysqli_query($conn,$sql1);
				
				$mobile1=$data1['mobile'];
				require_once('send_sms.php');
				
				
				send_sms($sms1,$mobile1);
				send_sms($sms2,$emp_mobile);
				


				if($data1['email']<>'')
				{
					$myname="Grievance Cell - ".$ulbname;
					$myemail=$ulb_info['myemail'];
					require_once('email_conf.php');

					$subject="Your Service assigned to ".$emp_list[$_POST['emp_id']]['emp_name'];
	
									
					//mail("\"".$data1['person_name']."\" <".$data1['email'].">", $subject, stripslashes($message), $headers, "-f$myemail");
				}



				$tpl->assign('msg','Successfully Updated');
			}
			else
			{
				
				
				$msg="Uable to insert   ".mysqli_error();
				$tpl->assign('msg',$msg);
			}
		}
		
		
		}
		

		$sql="select ward_id,ward_desc from ward_mst where ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
				$ward_list[$row['ward_id']]=$row['ward_desc'];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));	
			
		$sql="select street_id,street_desc from street_mst where ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
				$street_list[$row['street_id']]=$row['street_desc'];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));	
			
		$sql="select dept_id,dept_desc from dept_mst where ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
				$dept_list[$row['dept_id']]=$row['dept_desc'];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));	

		$sql="select desg_id,desg_desc from desg_mst where ulbid='".$_SESSION['ulbid']."'";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
				$desg_list[$row['desg_id']]=$row['desg_desc'];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));




		$sql="select grievance_origin_id,grievance_origin_desc from grievance_origin_mst";
		if($rs=mysqli_query($conn,$sql))
		{
			while($row = mysqli_fetch_assoc($rs))
				$grievance_origin_list[$row['grievance_origin_id']]=$row['grievance_origin_desc'];
		}
		else
			printf("Errormessage: %s\n", mysqli_error($conn));
										

	

		if(isset($_POST['grievance_id']))
		{
			 $sql="select cat3_id,app_type_id,person_name,email,hno,address,ward_id,street_id,mobile,comp_subject,comp_desc,grievance_origin_id,grievance_status_id,date_regd from grievances where grievance_id='".$_POST['grievance_id']."' and ulbid='".$_SESSION['ulbid']."'";
			if($rs=mysqli_query($conn,$sql))
			{
				$field_info = mysqli_fetch_fields($rs);
				$row = mysqli_fetch_assoc($rs);
				foreach($field_info as $fi => $f) 
					$data1[$f->name]=$row[$f->name];
			}
			else
				printf("Errormessage: %s\n", mysqli_error($conn));

			 $sql="select transaction_id,emp_id,alloted_date,disposed_date,disposal_status,disposal_remarks,app_type_id,rca,ca from grievances g, grievances_transactions gt where  g.grievance_id=gt.grievance_id and gt.grievance_id='".$_POST['grievance_id']."' and disposal_status='2' order by transaction_id";
			if($rs=mysqli_query($conn,$sql))
			{
				$transaction_id=0;
				while($row = mysqli_fetch_assoc($rs))
				{
					$data2[$row['transaction_id']]['emp_name']=$emp_list[$row['emp_id']]['emp_name'];
					$data2[$row['transaction_id']]['emp_desg']=$emp_list[$row['emp_id']]['emp_desg'];
					$data2[$row['transaction_id']]['emp_dept']=$emp_list[$row['emp_id']]['emp_dept'];
					$data2[$row['transaction_id']]['emp_mobile']=$emp_list[$row['emp_id']]['emp_mobile'];
					$data2[$row['transaction_id']]['alloted_date']=$row['alloted_date'];
					$data2[$row['transaction_id']]['disposed_date']=$row['disposed_date'];
					$data2[$row['transaction_id']]['disposal_status']=$row['disposal_status'];
					$data2[$row['transaction_id']]['disposal_remarks']=$row['disposal_remarks'];
					$data2[$row['transaction_id']]['rca']=$row['rca'];
					$data2[$row['transaction_id']]['ca']=$row['ca'];
					$transaction_id=$row['transaction_id'];
					$app_type_id=$row['app_type_id'];
				}
				$tpl->assign('data2',$data2);
				$tpl->assign('transaction_id',$transaction_id);
			}
			$tpl->assign('grievance_id',$_POST['grievance_id']);		
			$tpl->assign('data1',$data1);	
			

			if($app_type_id=='1')
			{
			$sql ="select cs_id,cs_desc as comp_desc from cs_mst";
		 $sql_status="select grievance_status_id,grievance_status_desc from grievance_status_mst where grievance_status_id IN('2','3','4','5','6','10')";
			}
			else
			{
			 $sql_status="select grievance_status_id,grievance_status_desc from grievance_status_mst where grievance_status_id IN('2','5','9','10')";
			$sql ="select cs_id,comp_desc from category3_mst where ulbid='".$_SESSION['ulbid']."'";
			}
			$rs = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_assoc($rs))
			{
			$cs_list[$row['cs_id']]=$row['comp_desc'];
			}
			$tpl->assign('cs_list',$cs_list);
            

           
    		if($rs=mysqli_query($conn,$sql_status))
    		{
    			while($row = mysqli_fetch_assoc($rs))
    				$grievance_status_list[$row['grievance_status_id']]=$row['grievance_status_desc'];
    		}
    		else
    			printf("Errormessage: %s\n", mysqli_error($conn));
    
    
    		}


		mysqli_free_result($rs);
					
		
		$tpl->assign('ward_list',$ward_list);
		$tpl->assign('street_list',$street_list);
		$tpl->assign('desg_list',$desg_list);
		$tpl->assign('dept_list',$dept_list);
		$tpl->assign('grievance_origin_list',$grievance_origin_list);				
		$tpl->assign('grievance_status_list',$grievance_status_list);
		$tpl->assign('main_icons',$obj->main_icons);
        $tpl->assign('banner',$_SESSION['banner']);
		$tpl->assign('services',$obj->services);
		$tpl->assign('uname',$_SESSION['user_name']);
		$tpl->assign('uid',$_SESSION['uid']);
		$tpl->display('manage_comp_sel.tpl');
	}
	else
	{
		$msg="You have not logged in, Please Login";
		$tpl->assign('msg',$msg);
		$tpl->display('user_login.tpl');
	}
?>