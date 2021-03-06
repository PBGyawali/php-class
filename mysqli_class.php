<?php
//this is a mysqli class for mysqli database
class mysqliclass
{
	protected $db;
	public $query;
	protected $statement;

    function __construct()    
	{
        $this->db = new mysqli(DB_SERVER , DB_USERNAME , DB_PASSWORD , DB_NAME);
        if ($this->db -> connect_errno) 
        {
        	echo "Failed to connect to MySQL server: " . $this->db -> connect_error;
        	exit();
        }                   
        else{
				if (session_status() === PHP_SESSION_NONE){session_start();}
				$this->db->set_charset('utf8');
				set_time_limit(0);
				return $this->db;
			}		
	}
	function execute($data = null, $types = "")
	{ 
		$this->statement = $this->db->prepare($this->query);
        if($data)	
        {     $data=$this->check_array($data);   
			if(!is_array($data))
			   {
				$types = $types ?: str_repeat("s", 1);
				$this->statement->bind_param($types, $data);
			   }
			else
			   {
				$types = $types ?: str_repeat("s", count($data));
				$this->statement->bind_param($types, ...$data);
			   }            			             
        }        
		return $this->statement->execute();		
	}
//count rows
	function row_count(){
		return $this->statement->get_result()->num_rows;
	}
	//extract result from executed statement
	function get_result(){
		return $this->statement->get_result();
	}
//get all array
	function statement_result(){
		return $this->statement->get_result()->fetch_all(MYSQLI_ASSOC);
	}
//get a single array
	function get_array(){		
		return $this->statement->get_result()->fetch_assoc();
    }
//closee the mysqli statement
    function close(){
        $this->statement->close();
	}
	//get affected rows
	function id(){
		return $this->db->insert_id;
	}
	function row(){
		return $this->db->affected_rows;
    }

//check if the executed data is an array or not, if not turn it into array
	function check_array($value)	{
		if ($value==null)
			return array();
		else if (is_array($value))
			return $value;
		else
			return array($value);	
	}
//creation of placeholdder "?" for prepared statment
	function create_placeholder($value)	{
		if (is_array($value))
		{	$marker=array();
			foreach($value as $values)
			{	$values='?'; 
			array_push($marker,$values);      
			}				
		  return $marker;
		}
		else{
		return array('?');	}
	}

	
//insert into table
	function insert($table,$column,$value)
	{			
		$column=$this->check_array($column);
		$column_condition=$this->implode_array($column,'',',');
		$value=$this->check_array($value);   		
		$marker= implode(', ', array_fill(0, sizeof($column), '?'));
		$this->query = "INSERT INTO $table ($column_condition) VALUES($marker)";		
		return $this->execute($value);
	}

//update a table
	function UpdateDataColumn($table,$column,$value=null,$placeholder,$condition,$combine='AND')
	{	
		if($value==null)
			$column_condition=$this->implode_array($column);
		else
			$column_condition=$this->implode_array($column,'?',',');
		$value=$this->check_array($value);
		$condition=$this->check_array($condition);
		$placeholder_condition=$this->implode_array($placeholder,'?',$combine);
		$finalvalue=array_merge($value,$condition);
		$this->query = "UPDATE $table SET $column_condition WHERE $placeholder_condition ";		
		return $this->execute($finalvalue);
	}
	//count table
	function CountTable($table,$condition=null,$value=null,$combine='AND')
	{   
		if ($value!=null)
		{		  
			$marker=$this->create_placeholder($condition);	  
			if(!is_array($condition)&&is_array($value))			
			  	$marker=$this->create_placeholder($value);
		   $row_condition=$this->implode_array($condition,$marker,$combine);
		}	 
	  	else	  
			$row_condition=$this->implode_array($condition);	  			  
	 	$this->query = "SELECT Count(*) AS total FROM $table ";	
		if(!empty($row_condition)&& is_string($row_condition))		
			$this->query .= " WHERE ".$row_condition;
		$this->execute($value);
		$row= $this->get_array();	
		return $row['total'];
	}

//gives specific column
	function get_data($data=null,$table=null,$placeholder=null,$conditions=null,$combine='AND',$limit=null)
	{    
		if (empty($table)) 
		return null;
		if(!$data)
			$requireddata="*";
		else	
			$requireddata=$this->implode_array($data,'',',');
		if ($conditions!=null)
			$row_condition=$this->implode_array($placeholder,'?',$combine);
		else
			$row_condition=$this->implode_array($placeholder);
		$this->query ="SELECT $requireddata FROM $table";
		if(!empty($row_condition)&& is_string($row_condition))
				$this->query .= " WHERE ".$row_condition;			
		if($limit)
			$this->query .= " LIMIT ".$limit; 
		$this->execute($conditions);
		$row= $this->get_array();	
		if ($row)
		{	if(!$data || is_array($data))			
				return $row;	
			else
				return $row[$data];	
		}						
		return null;
	}
//gives one array
	function getArray($table,$placeholder=null,$conditions=null,$combine='AND',$limit=null)
	{		
		return $this->get_data('',$table,$placeholder,$conditions,$combine,$limit);
	}
	  
//gives all row as an array
	function getAllArray($table,$column=null,$value=null,$combine='AND',$limit=null,$orderby=null,$order='DESC')
	{	
		  if ($value!=null)
		  $row_condition=$this->implode_array($column,'?',$combine);
		  else
		  $row_condition=$this->implode_array($column);
		  $this->query ="SELECT * FROM $table";
		  if(!empty($row_condition))	{ $this->query .= " WHERE ".$row_condition;	}
		  if(!empty($orderby))  {	$this->query .= " ORDER BY ". $orderby ." ".$order;}
		  if(!empty($limit))	{ $this->query .= " LIMIT ".$limit;	}
		  
		  $this->execute($value);
		  return $this->statement_result();
	  }
//delete a  column
	function Delete($table,$placeholder=NULL,$conditions=NULL,$combine='AND'){
		$row_condition=$this->implode_array($placeholder,'?',$combine); 
		$this->query ="DELETE FROM $table WHERE ". $row_condition;
		return $this->execute($conditions);	
	}
//gives sum of a column
	function total($table,$column,$placeholder,$value,$combine='AND')
	{
			$value=$this->check_array($value);
				$row_condition=$this->implode_array($placeholder,'?',$combine);
				$this->query = "SELECT IFNULL(SUM($column), 0) AS total FROM $table ";
				if(!empty($row_condition)) 
					$this->query .= " WHERE ".$row_condition;				
				$this->execute($value);
				$row=$this->get_array();
				if ($row)
						return $row['total'];				
				return 0;
	}

	function implode_array($placeholder=null,$conditions=null,$combine='AND')
		{
				$finalarray=array();
				if ($placeholder==null)
					return null;
				elseif (!is_array($placeholder)&&!empty($placeholder) && empty($conditions))						
					return " ".$placeholder." "; 
				elseif (!is_array($placeholder) && !empty($conditions) && !is_array($conditions))
				{		
					if	($conditions=='?')
						return " ".$placeholder."="."?"." ";
					else
						return " ".$placeholder."="."'".$conditions."'"." "; 
				}
				elseif (is_array($placeholder)&& empty($conditions))				
					return implode($combine, $placeholder);
				elseif (is_array($placeholder)&& is_array($conditions) && !empty($conditions))
					{							
							if ( in_array('?',$conditions))
							{
								foreach($conditions as $key=> $condition)
								{
									$result=" ".$placeholder[$key]."=".$condition." "; 
									array_push($finalarray,$result);
								}
							}
							else
							{
								foreach($conditions as $key=> $condition)
								{
									$result=" ".$placeholder[$key]."="."'".$condition."'"." "; 
									array_push($finalarray,$result);
								}									
							}
					}
				elseif (is_array($placeholder)&& !is_array($conditions) && !empty($conditions))
				{				
							if ($conditions=='?')
							{			
										foreach($placeholder as $key=> $place)
										{
											$result=" ".$place."=".$conditions." "; 
											array_push($finalarray,$result);
										}
							}
							else{
										foreach($placeholder as $key=> $place)
										{
											$result=" ".$place."="."'".$conditions."'"." "; 
											array_push($finalarray,$result);
										}
							}				
				}
				else
					{
							if (!empty($conditions))
							{
									if ( in_array('?',$conditions))
								{
									foreach($conditions as $key=> $condition)
									{
										$result=" ".$placeholder."=".$condition." "; 
										array_push($finalarray,$result); 
									}
								}

								else
								{
									foreach($conditions as $key=> $condition)
									{
										$result=" ".$placeholder."="."'".$condition."'"." "; 
										array_push($finalarray,$result);      
									}
								}
								
							}
							else							
								return null;
													
					}
				if ($combine)
					return implode($combine,$finalarray); 
				return  $finalarray;    
		}
}


?>
