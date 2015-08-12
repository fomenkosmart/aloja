<?php

namespace alojaweb\inc;

use alojaweb\Container;

class Utils
{
    public function __construct()
    {

    }

    public static function delete_none($array)
    {
        if (($key = array_search('None', $array)) !== false) {
            unset ($array[$key]);
        }

        return $array;
    }

    public static function getConfig($items) {
    	$concatConfig = "";
    	foreach($items as $item) {
	    	if ($item != 'bench') {
	    		if ($concatConfig) $concatConfig .= ",'_',";
	    	
	    		if ($item == 'id_cluster') {
	    			$concatConfig .= "CONCAT_WS(',',provider,vm_size,CONCAT(datanodes,' datanodes'))";
	    		} elseif ($item == 'iofilebuf') {
	    			$confPrefix = 'I';
	    		} elseif ($item == 'vm_OS') {
                    $confPrefix = 'OS';
                } else {
	    			$confPrefix = $item;
	    		}
	    	
	    		//avoid alphanumeric fields
	    		if ($item != 'id_cluster' && !in_array($item, array('net', 'disk'))) {
	    			$concatConfig .= "'".$confPrefix."', $item";
	    		} else if($item != 'id_cluster') {
	    			$concatConfig .= " $item";
	    		}
	    	}
    	}
    	
    	return $concatConfig;
    }

    public static function generateJSONTable($csv, $show_in_result, $precision = null, $type = null)
    {
        $jsonData = array();

        $i = 0;
        foreach ($csv as $value_row) {
            $jsonRow = array();
            $jsonRow[] = $value_row['id_exec'];
            if(key_exists("cluster_name",$value_row))
           	 $clusterName = $value_row['cluster_name'];
            
            foreach (array_keys($show_in_result) as $key_name) {
                if ($precision !== null && is_numeric($value_row[$key_name])) {
                    $value_row[$key_name] = round($value_row[$key_name], $precision);
                }

                if (!$type) {
                	if ($key_name == 'bench') {
                        $jsonRow[] = $value_row[$key_name];
                    } elseif ($key_name == 'init_time') {
                        $jsonRow[] = date('YmdHis', strtotime($value_row['end_time']));
                    } elseif ($key_name == 'exe_time') {
                        $jsonRow[] = round($value_row['exe_time']);
                    } elseif ($key_name == 'files') {
                        $jsonRow[] = $value_row['exec'];
                    } elseif ($key_name == 'prv' || $key_name == 'counters') {
                        $jsonRow[] = $value_row['id_exec'];
                    } elseif ($key_name == 'version') {
                        $jsonRow[] = "1.0.3";
                    } elseif ($key_name == 'cost') {
                        $jsonRow[] = number_format($value_row['cost'], 2);
                    } elseif ($key_name == 'id_cluster') {
                        //if (strpos($value_row['exec'], '_az')) $jsonRow[] = 'Azure L';
                        //else $jsonRow[] = "Local 1";
                        $jsonRow[] = $value_row['cluster_name'];
                    } elseif (stripos($key_name, 'BYTES') !== false) {
                        $jsonRow[] = round(($value_row[$key_name])/(1024*1024));
                    } elseif ($key_name == 'FINISH_TIME') {
                        $jsonRow[] = date('YmdHis', round($value_row[$key_name]/1000));
                    } elseif ($key_name == 'comp') {
                    	$jsonRow[] = self::getCompressionName($value_row[$key_name]);
                    } else
                        $jsonRow[] = $value_row[$key_name];
                } else {
                    if ($key_name == 'JOBID') {
                        $jsonRow[] = $value_row[$key_name];
                    } elseif (stripos($key_name, 'BYTES') !== false) {
                        $jsonRow[] = round(($value_row[$key_name])/(1024*1024));
                    } elseif (stripos($key_name, 'TIME') !== false) {
                        $jsonRow[] = substr($value_row[$key_name], -8);
                    } elseif (strpos($key_name, 'JOBNAME') !== false) {
                        if (strlen($value_row[$key_name]) > 15)
                            $jsonRow[] = substr($value_row[$key_name], 0, 15).'.';
                        else
                            $jsonRow[] = $value_row[$key_name];
                    } else {
                        $jsonRow[] = $value_row[$key_name];
                    }

                }
            }
            $jsonData[] = $jsonRow;
            $i++;
        }
        return $jsonData;
        //return json_encode(array('aaData' => $jsonData));
    }

    public static function get_GET_execs()
    {
        $execs = array();
        if (isset($_GET['execs'])) {
            $execs_tmp = array_unique($_GET['execs']);
            foreach ($execs_tmp as $exec) {
                $execs[] = filter_var($exec, FILTER_SANITIZE_NUMBER_INT);
            }
        }

        return $execs;
    }

    public static function get_GET_string($param)
    {
        if (isset($_GET[$param]))
            return filter_var($_GET[$param], FILTER_SANITIZE_STRING);
    }

    public static function get_GET_int($param)
    {
        if (isset($_GET[$param]))
            return filter_var($_GET[$param], FILTER_SANITIZE_NUMBER_INT);
    }

    public static function get_GET_float($param)
    {
        if (isset($_GET[$param]))
            return filter_var($_GET[$param], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function minimize_array($array)
    {
        foreach ($array as $key=>$value) {
            if (is_numeric($value))
                $array[$key] = round($value, 2);
        }

        return $array;
    }

    public static function minimize_exec_rows(array $rows, $stacked = false)
    {
        $minimized_rows = array();
        $max = null;
        $min = null;
        foreach ($rows as $key_row=>$row) {
            if (is_array($row)) {

                //if (is_numeric($row['id_exec'])) $id = $row['id_exec'];
                //else $id = $key_row;
                $id = $key_row;

                $row_sum = 0;
                foreach ($row as $key_field=>$field) {
                    if (is_numeric($field)) {
                        $field = round($field, 2);
                        if (!$stacked && $key_field != 'time') {
                            if (!$max || $field > $max) $max = $field;
                            if (!$min || $field < $min) $min = $field;
                        } else {
                            $row_sum += $field;
                        }
                    }
                    $minimized_rows[$id][$key_field] = $field;
                }
                if ($stacked) {
                    if (!$max || $row_sum > $max) $max = $row_sum;
                    if (!$min || $row_sum < $min) $min = $row_sum;
                }
            } else {
                throw new \Exception("Incorrect array format!");
            }
        }

        return array($minimized_rows, $max, $min);
    }

    public static function csv_to_array($filename='', $delimiter=',')
    {
        if(!file_exists($filename) || !is_readable($filename))

            return FALSE;

        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if(!$header)
                    $header = $row;
                else
                    $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }

        return $data;
    }

    public static function find_config($config, $csv)
    {
        $return = false;
        foreach ($csv as $value_row) {
            if ($value_row['exec'] == $config) {
                $value_row['print_name'] =
                "<strong>".$value_row['bench']."</strong> ".
                substr($value_row['exec'], 16, (strpos($value_row['exec'],'/')-16)).
                " {$value_row['exe_time']} secs.";
                $return = $value_row;
                break;
            }
        }

        return $return;
    }

    public static function generate_show($show_in_result, $csv, $offset)
    {
        reset($csv);
        $header = current($csv);

        $dont_show= array('job_name');

        $position = 0;
        foreach (array_keys($header) as $key_header) {
            if ($position > $offset && !in_array($key_header, $dont_show)) {

                $name = str_replace('_', ' ', $key_header);

                if (stripos($key_header, 'BYTES') !== false) {
                    $show_in_result[$key_header] = str_ireplace('BYTES', 'MB', $name);
                } else {
                    $show_in_result[$key_header] = $name;
                }
            }
            $position++;
        }

        return $show_in_result;
    }
    
    public static function getExecsOptions($db,$where_configs = "")
    {
        $filter_execs = $where_configs ." ".DBUtils::getFilterExecs();

        $benchOptions = $db->get_rows("SELECT DISTINCT bench FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$netOptions = $db->get_rows("SELECT DISTINCT net FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$diskOptions = $db->get_rows("SELECT DISTINCT disk FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$mapsOptions = $db->get_rows("SELECT DISTINCT maps FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$compOptions = $db->get_rows("SELECT DISTINCT comp FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$blk_sizeOptions = $db->get_rows("SELECT DISTINCT blk_size FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$clusterOptions = $db->get_rows("SELECT DISTINCT c.name FROM execs e JOIN clusters c USING(id_cluster) WHERE  valid = 1 AND filter = 0 $filter_execs");
    	$clusterNodes = $db->get_rows("SELECT DISTINCT c.datanodes FROM execs e JOIN clusters c USING(id_cluster) WHERE valid = 1 AND filter = 0 $filter_execs");
    	$hadoopVersion = $db->get_rows("SELECT DISTINCT hadoop_version FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
        $benchType = $db->get_rows("SELECT DISTINCT bench_type FROM execs e JOIN clusters c USING(id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
    	$vmOS = $db->get_rows("SELECT DISTINCT vm_OS FROM execs e JOIN clusters USING (id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");
        $execTypes = $db->get_rows("SELECT DISTINCT exec_type FROM execs e JOIN clusters USING (id_cluster) WHERE 1 AND valid = 1 AND filter = 0 $filter_execs");

        $discreteOptions = array();
    	$discreteOptions['bench'][] = 'All';
    	$discreteOptions['net'][] = 'All';
    	$discreteOptions['disk'][] = 'All';
    	$discreteOptions['maps'][] = 'All';
    	$discreteOptions['comp'][] = 'All';
    	$discreteOptions['blk_size'][] = 'All';
    	$discreteOptions['id_cluster'][] = 'All';
    	$discreteOptions['datanodes'][] = 'All';
    	$discreteOptions['hadoop_version'][] = 'All';
        $discreteOptions['bench_type'][] = 'All';
        $discreteOptions['vm_OS'][] = 'All';
        $discreteOptions['exec_type'][] = 'All';

        foreach($benchOptions as $option) {
    		$discreteOptions['bench'][] = array_shift($option);
    	}
    	foreach($netOptions as $option) {
    		$current = array_shift($option);
    		$current = ($current == "0") ? "HDI" : $current;
    		$discreteOptions['net'][] = $current;
    	}
    	foreach($diskOptions as $option) {
    		$current = array_shift($option);
    		$current = ($current == "0") ? "HDI" : $current;
    		$discreteOptions['disk'][] = $current;
    	}
    	foreach($mapsOptions as $option) {
    		$discreteOptions['maps'][] = array_shift($option);
    	}
    	foreach($compOptions as $option) {
    		$value = array_shift($option);
    		$discreteOptions['comp'][] = self::getCompressionName($value);
    	}
    	foreach($blk_sizeOptions as $option) {
    		$discreteOptions['blk_size'][] = array_shift($option);
    	}
    	foreach($clusterOptions as $option) {
            $discreteOptions['id_cluster'][] = array_shift($option);
    	}
    	foreach($clusterNodes as $option) {
    		$discreteOptions['datanodes'][] = array_shift($option);
    	}
    	foreach($hadoopVersion as $option) {
    		$discreteOptions['hadoop_version'][] = array_shift($option);
    	}
        foreach($benchType as $option) {
            $discreteOptions['bench_type'][] = array_shift($option);
        }
        foreach($vmOS as $option) {
            $discreteOptions['vm_OS'][] = array_shift($option);
        }
        foreach($execTypes as $option) {
            $discreteOptions['exec_type'][] = array_shift($option);
        }

    	return $discreteOptions;
    }
    
    public static function getCompressionName($compCode)
    {
    	$compName = '';
    	if($compCode == 0)
    		$compName = 'None';
    	elseif($compCode == 1)
    		$compName = 'ZLIB';
    	elseif($compCode == 2)
    		$compName = 'BZIP2';
    	else
    		$compName = 'Snappy';
    	
    	return $compName;
    }

    public static function getClusterName($clusterCode, $db)
    {
        $clusterName = $db->get_rows("SELECT name FROM clusters WHERE id_cluster=$clusterCode");

        return $clusterName[0]['name'];
    }
    
    public static function getNetworkName($netShort)
    {
    	$netName = '';
    	if($netShort == 'IB')
    		$netName = 'InfiniBand';
    	elseif($netShort == 'HDI')
    		$netName = 'HDInsight';
    	else
    		$netName = 'Ethernet';
    	
    	return $netName;
    }
    
    public static function getDisksName($diskShort)
    {
    	$disks = '';
    	if($diskShort == 'HDD')
    		$disks = 'SATA drive';
    	elseif($diskShort == 'SSD')
    		$disks = 'SSD drive';
    	elseif($diskShort == "HDI")
    		$disks = 'Azure Storage (remote)';
    	else if(preg_match("/^RL/",$diskShort))
    		$disks = substr($diskShort,2).' HDFS remote(s) /tmp  to local SATA disk';
    	else if(preg_match("/^RR/",$diskShort))
    		$disks = substr($diskShort,2).' Remote volumes(s)';
    	else if(preg_match("/^SS([0-9]+)/",$diskShort))
    		$disks = substr($diskShort,2).' SSD drives';
    	else if(preg_match("/^HS([0-9]+)/",$diskShort))
    		$disks = substr($diskShort,2).' HDFS in SATA /tmp to SSD';
        else if(preg_match("/^RS([0-9]+)/",$diskShort))
            $disks = substr($diskShort,2).' HDFS in Remote(s) /tmp to SSD';
    	else
    		$disks = substr($diskShort,2).' SATA drives';
    
    	return $disks;
    }
    
    public static function getBeautyRam($ramAmount)
    {
    	return round($ramAmount,0)." GB";
    }
    
    public static function makeExecInfoBeauty(&$execInfo)
    {
    	if(array_key_exists('comp',$execInfo))
    		$execInfo['comp'] = self::getCompressionName($execInfo['comp']);
    	
    	if(array_key_exists('net',$execInfo))
    		$execInfo['net'] = self::getNetworkName($execInfo['net']);
    	
    	if(array_key_exists('disk',$execInfo))
    		$execInfo['disk'] = self::getDisksName($execInfo['disk']);
    }
    
    public static function changeParamOptions(&$paramOptions, $paramEval)
    {
    	if($paramEval == 'comp') {
    		foreach($paramOptions as &$option) {
    			$option['param'] = Utils::getCompressionName($option['param']);
    		}
    	}
    }
    
    public static function getParamevalUnit($paramEval)
    {
    	$unit = '';
    	if($paramEval == 'iofilebuf')
    		$unit = 'KB';
    	else if($paramEval == 'blk_size')
    		$unit = 'MB';
    	
    	return $unit;
    }
    
    public static function getExecutionCost($exec, $costHour, $costRemote, $costSSD, $costIB) { 

    	$num_remotes = 0;
    	/** calculate remote */
    	if(preg_match("/^RL/", $exec['disk']) || preg_match("/^RR/", $exec['disk'])) {
    		$num_remotes = (int)$exec['disk'][2];
    	}
    	
    	/** calculate HDD */
    	if(preg_match("/^HD[0-9]/", $exec['disk'])) {
    		$num_remotes = (int)$exec['disk'][2];
    	}
    	
    	$num_ssds=0;
    	
    	
    	/** calculate Multiple SSDs */
    	if(preg_match("/^SS[0-9]/", $exec['disk'])) {
    		$num_ssds= (int)$exec['disk'][2];
    	}
    	
    	/** if local SSD, numSSDs + 1, remotes = num HDD */
    	if(preg_match("/^HS[0-9]/", $exec['disk'])) {
    		$num_ssds=1;
    		$num_remotes = (int)$exec['disk'][2];
    	}

    	$num_IB=0;
    	 
    	if($exec['net'] == "IB")
    		$num_IB = 1;
    	
    	if($exec['disk'] == "SSD")
    		$num_ssds = 1;
    	
    	if($exec['disk'] == 'HDD')
    		$num_remotes = 1;
    	
    	//To get the cost
        //convert the cluster cost + additions from per hour to per second, then just multiply by number of seconds it took
        $cost = $exec['exe_time']*(($costHour + ($costRemote * $num_remotes) + ($costIB * $num_IB) + ($costSSD * $num_ssds))/3600);

    	return $cost;
    }
    
    public static function getClustersInfo($dbUtils) {
    	$rows = $dbUtils->get_rows("SELECT * FROM clusters");

    	$clusters = array();
    	foreach($rows as $row) {
    		$clusters[$row['name']] = $row;
    	}
    	
    	return json_encode($clusters);
    }
}
