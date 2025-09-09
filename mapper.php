<?php
	declare(strict_types = 1);
	set_error_handler(function($errno, $errstr, $errfile, $errline): void
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}, E_ALL);
	
	require_once 'db2/src/Erorus/DB2/Reader.php';
	
	use \Erorus\DB2\Reader;
	
	enum Locale: int
	{
		case enUS = 0;
		case koKR = 1;
		case frFR = 2;
		case deDE = 3;
		case zhCN = 4;
		case zhTW = 5;
		case esES = 6;
		case xxYY = 7;
	}
	
	function db2_read(string $db2_file): array
	{
		$reader = new Reader($db2_file);
		$data = [];
		foreach($reader->generateRecords() as $id => $record)
		{
			array_unshift($record, $id);
			$data[] = $record;
		}
		
		return $data;
	}
	
	class Glossary
	{
		public array $data = [];
		public array $useful = [];
		
		function get(string $source): ?string
		{
			if(array_key_exists($source, $this->data))
				return $this->data[$source];
			return null;
		}
		
		function set(string $source, string $target): void
		{
			if(!array_key_exists($source, $this->data))
				$this->data[$source] = $target;
		}
		
		function useful(string $source, string $target): void
		{
			if(!array_key_exists($source, $this->useful))
				$this->useful[trim($source)] = trim($target);
		}
	}		
	
	class ConstFile
	{
		public string $file;
		public string $knot;
		
		function __construct(string $file)
		{
			$this->file = $file;
		}
		
		function knot(string $db2_file): void
		{
			$this->knot = $db2_file;
		}
	}
	
	class TableKnot
	{
		public array $id;
		public array $pair = [];
		
		function __construct(int ...$id)
		{
			$this->id = $id;
		}
		
		function pair(int $dbc_header_id, int $db2_header_id): TableKnot
		{
			$this->pair[$dbc_header_id] = $db2_header_id;
			return $this;
		}
	}
	
	class TableFile
	{
		public Locale $locale;
		public array $id;
		public array $knots = [];
		
		function __construct(Locale $locale, int ...$id)
		{
			$this->locale = $locale;
			$this->id = $id;
		}
		
		function knot(string $db2_file, int ...$id): TableKnot
		{
			return $this->knots[$db2_file] = new TableKnot(...$id);
		}
	}
	
	class LocaleKnot
	{
		public string $name;
		public array $key;
		public array $pair = [];
		
		function __construct(string $name, string ...$key)
		{
			$this->name = $name;
			$this->key = $key;
		}
		
		function pair(string $cdb_key, string $cdb_locale_key): LocaleKnot
		{
			$this->pair[$cdb_key] = $cdb_locale_key;
			return $this;
		}
	}
	
	class LocaleTable
	{
		public array $key;
		public LocaleKnot $knot;
		
		function __construct(string ...$key)
		{
			$this->key = $key;
		}
		
		function knot(string $cdb_locale_name, string ...$key): LocaleKnot
		{
			return $this->knot = new LocaleKnot($cdb_locale_name, ...$key);
		}
	}
	
	class Mapper
	{
		public array $dbc_list = [];
		public array $lua_list = [];
		public array $sql_list = [];
		public string $csv_path = 'csv';
		public string $db2_path = 'classic'.DIRECTORY_SEPARATOR.'dbfilesclient';
		public string $sql_name = 'classicmangos';
		public string $custom_path = 'custom';
		public string $output_path = 'output';
		public string $sql_path = 'localhost';
		private string $user;
		private string $password;
		
		function __construct(string $user, string $password)
		{
			$this->user = trim($user);
			$this->password = trim($password);
		}
		
		function assign_dbc(string $csv_file, Locale $locale, int ...$id): TableFile
		{
			return $this->dbc_list[$csv_file] = new TableFile($locale, ...$id);
		}
		
		function assign_lua(string $csv_file, string $file): ConstFile
		{
			return $this->lua_list[$csv_file] = new ConstFile($file);
		}
		
		function assign_sql(string $cdb_name, string ...$key): LocaleTable
		{
			return $this->sql_list[$cdb_name] = new LocaleTable(...$key);
		}
		
		function pull(Glossary &$glossary, bool $partial = true): void
		{
			foreach($this->dbc_list as $csv_file => $dbc_table)
			{
				$csv_input = fopen($this->csv_path.DIRECTORY_SEPARATOR.$csv_file, 'r');
				$csv_input_header = [];
				$csv_output = fopen($this->output_path.DIRECTORY_SEPARATOR.$csv_file, 'w');
				$csv_output_header = [];
				$locale_data = [];
				while($csv_row = fgetcsv($csv_input, escape: "\\"))
				{
					if(!$csv_input_header)
					{
						$csv_input_header = $csv_row;
						foreach($dbc_table->knots as $db2_file => $db2_knot)
						{
							foreach(db2_read($this->db2_path.DIRECTORY_SEPARATOR.$db2_file) as $db2_row)
							{
								$data =& $locale_data;
								foreach($db2_knot->id as $db2_header_id)
								{
									$data =& $data[$db2_row[$db2_header_id]];
									$data ??= [];
								}
								
								foreach($db2_knot->pair as $dbc_id => $db2_id)
								{
									if(trim($db2_row[$db2_id] ?? '') === '')
										continue;
									
									$data[$dbc_id] = $db2_row[$db2_id];
									$csv_output_header[$dbc_id] = $csv_input_header[$dbc_id];
								}
							}
						}
						
						if(file_exists($this->custom_path.DIRECTORY_SEPARATOR.$csv_file))
						{
							$custom_header = [];
							$custom_file = fopen($this->custom_path.DIRECTORY_SEPARATOR.$csv_file, 'r');
							while($custom_row = fgetcsv($custom_file, escape: "\\"))
							{
								if(!$custom_header)
								{
									$custom_header = $custom_row;
									continue;
								}
								
								$custom_keys = [];
								$custom_values = [];
								foreach($custom_row as $id => $custom_text)
								{
									$key_id = array_search(trim($custom_header[$id]), $csv_input_header);
									if($key_id === false)
										continue;
									
									if(in_array($key_id, $dbc_table->id))
										$custom_keys[$key_id] = $custom_text;
									elseif(trim($custom_text) !== '')
										$custom_values[$key_id] = $custom_text;
								}
								
								$data =& $locale_data;
								foreach($custom_keys as $id)
								{
									$data =& $data[trim($id)];
									$data ??= [];
								}
								
								foreach($custom_values as $id => $custom_text)
								{
									$data[$id] = $custom_text;
									$csv_output_header[$id] = $csv_input_header[$id];
								}
							}
							
							fclose($custom_file);
						}
						
						$output_header = [];
						foreach($dbc_table->id as $id)
							$output_header[$id] = $csv_input_header[$id];
						
						foreach($csv_output_header as $id => $key)
						{
							//$output_header[$id] = $csv_input_header[$id];
							$output_header[$id + $dbc_table->locale->value] = $csv_input_header[$id + $dbc_table->locale->value];
						}
						
						if($partial)
							ksort($output_header);
						else
							$output_header = $csv_row;
						
						fputcsv($csv_output, $output_header, escape: "\\", eol: "\r\n");
						continue;
					}
					
					$zz = ['zzold', 'zzdonot', 'dnd'];
					foreach($csv_row as $csv_text)
						foreach($zz as $z)
							if(str_contains(strtolower($csv_text), $z))
								$zz = [];
					
					if(!$zz)
					{
						if(!$partial)
							fputcsv($csv_output, $csv_row, escape: "\\", eol: "\r\n");
						continue;
					}
					
					$output_values = [];
					$dbc_format = "$csv_file@";
					$data =& $locale_data;
					foreach($dbc_table->id as $key_id)
					{
						$id = $csv_row[$key_id];
						$output_values[$key_id] = $id;
						$dbc_format .= "$id:";
						$data =& $data[trim($id)];
						$data ??= [];
					}
					
					$csv_output_values = [];
					foreach($csv_output_header as $id => $key)
					{
						$csv_text = $csv_row[$id] ?? '';
						if(trim($csv_text) === '')
							continue;
						
						$csv_output_values[$id] = $key;
						if(!array_key_exists($id, $data))
						{
							$data[$id] = $csv_text;
							echo "$dbc_format$key - MISSING\n-> $csv_text\n\n";
						}
					}
					
					if(!$csv_output_values)
					{
						if(!$partial)
							fputcsv($csv_output, $csv_row, escape: "\\", eol: "\r\n");
						continue;
					}
					
					foreach($csv_output_header as $id => $key)
					{
						if(!array_key_exists($id, $csv_output_values))
						{
							//$output_values[$id] = null;
							$output_values[$id + $dbc_table->locale->value] = null;
							continue;
						}
						
						$csv_text = $csv_row[$id];
						switch($csv_file)
						{
							case 'AuctionHouse.csv':
							case 'SpellFocusObject.csv':
							case 'LockType.csv':
							$output_text = $data[$id];
							break;
							default:
							$output_text = $glossary->get($csv_text) ?? $data[$id];
						}
						
						$glossary->set($csv_text, $output_text);
						switch($csv_file)
						{
							case 'AreaPOI.csv':
							case 'AreaTable.csv':
							case 'CreatureType.csv':
							case 'Faction.csv':
							case 'ItemSet.csv':
							case 'LFGDungeons.csv':
							case 'Map.csv':
							case 'SkillLine.csv':
							case 'Spell.csv':
							case 'SpellFocusObject.csv':
							case 'TaxiNodes.csv':
							case 'WMOAreaTable.csv':
							case 'ChrClasses.csv':
							case 'ChrRaces.csv':
							case 'CreatureFamily.csv':
							case 'FactionGroup.csv':
							case 'ItemBagFamily.csv':
							case 'ItemPetFood.csv':
							case 'ItemSubClass.csv':
							case 'Languages.csv':
							case 'SkillLineCategory.csv':
							case 'SpellShapeshiftForm.csv':
							case 'TalentTab.csv':
							switch($key)
							{
								case 'Name_enUS':
								case 'AreaName_enUS':
								case 'DisplayName_enUS':
								case 'VerboseName_enUS':
								case 'MapName_enUS':
								$glossary->useful($csv_text, $output_text);
							}
						}
						
						$output_text = str_replace("\\n", "\n", $this->sterilize($output_text));
						//$output_values[$id] = null;
						$output_values[$id + $dbc_table->locale->value] = $output_text;
						//$csv_row[$id] = null;
						$csv_row[$id + $dbc_table->locale->value] = $output_text;
						if(!$this->verify($csv_text, $output_text))
							echo "$dbc_format$key - INCOMPATIBLE\n-> $csv_text\n<- $output_text\n\n";
					}
					
					if($partial)
						ksort($output_values);
					else
						$output_values = $csv_row;
					
					fputcsv($csv_output, $output_values, escape: "\\", eol: "\r\n");
				}
				
				fclose($csv_input);
				fclose($csv_output);
			}
		}
		
		function pare(): void
		{
			foreach($this->lua_list as $csv_file => $lua_table)
			{
				$locale_data = [];
				if($lua_table->knot !== null)
					foreach(db2_read($this->db2_path.DIRECTORY_SEPARATOR.$lua_table->knot) as $db2_row)
						if(trim($db2_row[2] ?? '') !== '')
							$locale_data[$db2_row[1]] = $db2_row[2];
				
				if(file_exists($this->custom_path.DIRECTORY_SEPARATOR.$csv_file))
				{
					$csv_custom = fopen($this->custom_path.DIRECTORY_SEPARATOR.$csv_file, 'r');
					while($custom_row = fgetcsv($csv_custom, escape: "\\"))
						$locale_data[trim($custom_row[0])] = $custom_row[1];
				}
				
				$lua_output = fopen($this->output_path.DIRECTORY_SEPARATOR.$lua_table->file, 'w');
				$lua_output_format = "%s = \"%s\";";
				$lua_custom = file_exists($this->custom_path.DIRECTORY_SEPARATOR.$lua_table->file);
				if($lua_custom)
				{
					$lua_custom = fopen($this->custom_path.DIRECTORY_SEPARATOR.$lua_table->file, 'r');
					$insert = "-- <$csv_file>";
					while(($lua_line = fgets($lua_custom)) !== false)
					{
						if(str_contains($lua_line, $insert))
						{
							$lua_output_format = str_replace($insert, $lua_output_format, $lua_line);
							break;
						}
						
						fwrite($lua_output, $lua_line);
					}
				}
				
				$csv_input = fopen($this->csv_path.DIRECTORY_SEPARATOR.$csv_file, 'r');
				while($csv_row = fgetcsv($csv_input, escape: "\\"))
				{
					$csv_key = $csv_row[0];
					$csv_text = $csv_row[1] ?? '';
					if(trim($csv_text) === '')
						continue;
					
					if(array_key_exists($csv_key, $locale_data))
					{
						$lua_text = $locale_data[$csv_key];
						if(str_ends_with($csv_key, '_P1') or !array_key_exists($csv_key.'_P1', $locale_data))
							$lua_text = preg_replace("/\\|4.+?:.+?:(.+?);/", "$1", $lua_text);
						
						$lua_text = str_replace("\\\"", "\"", $this->sterilize($lua_text));
						if($csv_text !== $lua_text)
						{
							fwrite($lua_output, sprintf($lua_output_format, $csv_key, str_replace("\"", "\\\"", $lua_text)));
							if(!$this->verify($csv_text, $lua_text))
								echo "$csv_file@$csv_key - INCOMPATIBLE\n-> $csv_text\n<- $lua_text\n\n";
						}
					}
					else
						echo "$csv_file@$csv_key - MISSING\n-> $csv_text\n\n";
				}
				
				fclose($csv_input);
				
				if($lua_custom)
				{
					while(($lua_line = fgets($lua_custom)) !== false)
						fwrite($lua_output, $lua_line);
					fclose($lua_custom);
				}
				
				fclose($lua_output);
			}
		}
		
		function dump(string $sql_file, string $locale, Glossary &$glossary, bool $create_custom = false): void
		{
			$sql = new mysqli($this->sql_path, $this->user, $this->password);
			$sql_output = fopen($this->output_path.DIRECTORY_SEPARATOR.$sql_file, 'w');
			foreach($this->sql_list as $cdb_name => $sql_table)
			{
				if($sql_table->knot === null)
					continue;
				
				$locale_data = [];
				$locale_keys = array_values($sql_table->knot->pair);
				$locale_query_keys = implode(', ', [...$sql_table->knot->key, ...$locale_keys]);
				$locale_query = "SELECT %s FROM %s.%s";
				if($sql_table->knot->name === 'broadcast_text_locale')
					$locale_query .= " WHERE Locale = '$locale'";
				
				$locale_query = $sql->query(sprintf($locale_query, $locale_query_keys, $this->sql_name, $sql_table->knot->name));
				while($locale_row = $locale_query->fetch_assoc())
				{
					$data =& $locale_data;
					foreach($sql_table->knot->key as $key)
					{
						$data =& $data[$locale_row[$key]];
						$data ??= [];
					}
					
					foreach($sql_table->knot->pair as $cdb_key => $locale_key)
						if(trim($locale_row[$locale_key] ?? '') !== '')
							$data[$cdb_key] = $locale_row[$locale_key];
				}
				
				$cdb_keys = array_keys($sql_table->knot->pair);
				$custom_file = file_exists($this->custom_path.DIRECTORY_SEPARATOR.$cdb_name.'.csv');
				if($custom_file)
				{
					$custom_header = [];
					$custom_file = fopen($this->custom_path.DIRECTORY_SEPARATOR.$cdb_name.'.csv', 'r');
					while($custom_row = fgetcsv($custom_file, escape: "\\"))
					{
						if(!$custom_header)
						{
							$custom_header = $custom_row;
							continue;
						}
						
						$custom_keys = [];
						$custom_values = [];
						foreach($custom_row as $id => $custom_text)
						{
							$key = trim($custom_header[$id]);
							$key_id = array_search($key, $sql_table->key);
							if($key_id !== false)
								$custom_keys[$key_id] = $custom_text;
							elseif(trim($custom_text) !== '' and in_array($key, $cdb_keys))
								$custom_values[$key] = $custom_text;
						}
						
						$data =& $locale_data;
						foreach($custom_keys as $key)
						{
							$data =& $data[trim($key)];
							$data ??= [];
						}
						
						foreach($custom_values as $key => $custom_text)
							$data[$key] = $custom_text;
					}
					
					fclose($custom_file);
				}
				
				if($sql_table->knot->name === 'broadcast_text_locale')
					$locale_query_keys = implode(', ', [...$sql_table->knot->key, 'Locale', ...$locale_keys, 'VerifiedBuild']);
				
				$dump = fopen("dump/$cdb_name.csv", 'w');
				fputcsv($dump, [...$sql_table->knot->key, ...$cdb_keys], escape: "\\", eol: "\r\n");
				fwrite($sql_output, sprintf("INSERT IGNORE INTO %s (%s)\nVALUES", $sql_table->knot->name, $locale_query_keys));
				
				$custom_data = [];
				$cdb_query_keys = implode(', ', [...$sql_table->key, ...$cdb_keys]);
				$cdb_query = $sql->query(sprintf("SELECT %s FROM %s.%s", $cdb_query_keys, $this->sql_name, $cdb_name));
				while($cdb_row = $cdb_query->fetch_assoc())
				{
					$dump_row = [];
					$output_format = "\n\t(";
					$cdb_format = "$cdb_name@";
					$data =& $locale_data;
					foreach($sql_table->key as $key)
					{
						$id = $cdb_row[$key];
						$dump_row[] = $id;
						$output_format .= "$id, ";
						$cdb_format .= "$id:";
						$data =& $data[$id];
						$data ??= [];
					}
					
					$dump_values = [];
					$output_values = [];
					foreach($sql_table->knot->pair as $cdb_key => $output_key)
					{
						$cdb_text = $cdb_row[$cdb_key] ?? '';
						if(trim($cdb_text) === '')
							continue;
						
						$output_text = $glossary->get($cdb_text);
						if(array_key_exists($cdb_key, $data) and ($output_text === null or $sql_table->knot->name !== 'broadcast_text_locale'))
							$output_text = $data[$cdb_key];
						
						if($output_text === null)
						{
							$data =& $custom_data;
							foreach($sql_table->key as $key)
							{
								$data =& $data[$cdb_row[$key]];
								$data ??= [];
							}
							
							$data[$cdb_key] = $cdb_text;
							echo "$cdb_format$cdb_key - MISSING\n-> $cdb_text\n\n";
						}
						else
						{
							if($sql_table->knot->name === 'locales_gossip_menu_option')
								$output_text = str_replace('Я хотел бы', 'Я хочу', preg_replace("/\\\$[gG]([^:;]+):[^:;]+;/", "$1", $output_text));
							
							if($sql_table->knot->name !== 'mangos_string')
								$glossary->set($cdb_text, $output_text);
							
							switch($cdb_name)
							{
								case 'creature_template':
								case 'gameobject_template':
								case 'gossip_menu_option':
								case 'item_template':
								case 'points_of_interest':
								case 'quest_template':
								switch($cdb_key)
								{
									case 'Name':
									case 'SubName':
									case 'name':
									case 'option_text':
									case 'icon_name':
									case 'Title':
									$glossary->useful($cdb_text, $output_text);
								}
							}
							
							$output_text = $this->sterilize($output_text);
							$dump_values[$cdb_key] = $output_text;
							$output_values[$output_key] = sprintf("'%s'", str_replace("'", "\\'", $output_text));
							if(!$this->verify($cdb_text, $output_text))
								echo "$cdb_format$cdb_key - INCOMPATIBLE\n-> $cdb_text\n<- $output_text\n\n";
						}
					}
					
					if($dump_values)
					{
						foreach($cdb_keys as $key)
							$dump_row[] = $dump_values[$key] ?? '';
						fputcsv($dump, $dump_row, escape: "\\", eol: "\r\n");
					}
					
					if($output_values)
					{
						$values = [];
						foreach($locale_keys as $key)
							$values[] = $output_values[$key] ?? "''";
						
						if($sql_table->knot->name === 'broadcast_text_locale')
						{
							$output_format .= "'$locale', ";
							$values[] = '0';
						}
						
						$output_format .= sprintf("%s),", implode(', ', array_values($values)));
						fwrite($sql_output, $output_format);
					}
				}
				
				fclose($dump);
				
				if($create_custom and !$custom_file and $custom_data)
				{
					$custom_file = fopen($this->custom_path.DIRECTORY_SEPARATOR.$cdb_name.'.csv', 'w');
					$output = function(array $data, array $id = []) use (&$output, &$sql_table, &$custom_file): void
					{
						$cdb_keys = array_keys($sql_table->knot->pair);
						foreach($data as $inner_id => $inner_data)
						{
							$inner_key = [...$id, $inner_id];
							if(count($inner_key) === count($sql_table->key))
							{
								foreach($cdb_keys as $key)
									$inner_key[] = $inner_data[$key] ?? '';
								
								fputcsv($custom_file, $inner_key, escape: "\\", eol: "\r\n");
							}
							else
								$output($inner_data, $inner_key);
						}
					};
					
					fputcsv($custom_file, [...$sql_table->key, ...$cdb_keys], escape: "\\", eol: "\r\n");
					$output($custom_data);
					fclose($custom_file);
				}
				
				$output_format = "\nON DUPLICATE KEY UPDATE";
				if($sql_table->knot->name === 'broadcast_text_locale')
					$locale_keys[] = 'VerifiedBuild';
				
				foreach($locale_keys as $key)
					$output_format .= "\n\t$key = VALUES($key),";
				
				fseek($sql_output, -1, SEEK_CUR);
				fwrite($sql_output, $output_format);
				fseek($sql_output, -1, SEEK_CUR);
				fwrite($sql_output, ";\n");
			}
			
			fclose($sql_output);
			$sql->close();
		}
		
		function verify(string $text_is, string $text_to): bool
		{
			$compare = function(string $pattern, string $is, string $to, bool $no_keys): bool
			{
				$sort = function(array $match) use ($no_keys): array
				{
					$token = [];
					foreach($match[0] as $id => $text)
					{
						$token_id = $match['id'][$id];
						$token_text = $match['token'][$id];
						if($no_keys)
							$token[] = strtolower($token_text.$token_id);
						elseif(!$token_id)
							$token[$id] = $token_text;
						else
							$token[(int)$token_id - 1] = $token_text;
					}
					
					if($no_keys)
					{
						$token = array_unique(array_values($token));
						sort($token);
					}
					
					return $token;
				};
				
				preg_match_all($pattern, $is, $match_is);
				preg_match_all($pattern, $to, $match_to);
				
				if(!isset($match_is) and !isset($match_to))
					return true;
				if(!isset($match_is) or !isset($match_to))
					return false;
				
				return $sort($match_is) == $sort($match_to);
			};
			
			$lua_pattern = "/%(?:(?<id>[1-9])\\$)?(?<token>(?:0\\d|\\.\\d)?[cdfgs])/";
			$dbc_pattern = "/(?<!%[1-9])\\$(?<token>(?:[*\\/]?\\d+)?[adefhimoqstvxz])(?<id>[1-9])?/i";
			return $compare($lua_pattern, $text_is, $text_to, false) and $compare($dbc_pattern, $text_is, $text_to, true);
		}
		
		function sterilize(string $text): string
		{
			$patterns =
			[
				"/\\|\\d-\\d\\((.+?)\\)/",
				"/\\|4([^:;]+):[^:;]+:[^:;]+;/",
				"/(\\\$[lL][^:;]+:)[^:;]+:([^:;]+;)/",
				"/(\\\$[gG][^:;]+:[^:;]+):[^:;]+(;)/",
				"/\\|Hchannel.+?(\\[.+?\\])\\|h/",
				"/\\*([^\\*$]+)\\*/",
				"/(?:\r\n|\n)/",
				"/\\\\32/"
			];
			$replaces =
			[
				"$1",
				"$1",
				"$1$2",
				"$1$2",
				"$1",
				"<$1>",
				"\\n",
				" "
			];
			
			return preg_replace($patterns, $replaces, $text);
		}
		
		function exe_patch(string $exe_name): void
		{
			if(!file_exists($exe_name))
				return;
			
			$exe_file = fopen($exe_name, 'r+b');
			fseek($exe_file, 0x2f11e4);
			fwrite($exe_file, chr(0x0));
			fclose($exe_file);
		}
	}
	
	class Translator
	{
		public string $endpoint;
		private string $key;
		
		function __construct($key)
		{
			$this->key = trim($key);
			$this->endpoint = str_ends_with($this->key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
		}
		
		function fetch(string $path, array $context, int $retry = 10): string
		{
			$stream = fopen($this->endpoint.$path, 'r', context: stream_context_create($context));
			$header = stream_get_meta_data($stream)['wrapper_data'];
			$status = explode(' ', $header[0], 3);
			switch($status[1])
			{
				case 429:
				case 500:
					fclose($stream);
					echo "Retry after $retry seconds...\n"; 
					sleep(10);
					return $this->fetch($path, $context, $retry * 2);
				case 456:
					fclose($stream);
					die($status[2]);
				default:
					$content = stream_get_contents($stream);
					fclose($stream);
					return $content;
			}
		}	
		
		function put(string $path, string $method, array $data): array
		{
			$query = json_encode($data);
			$context =
			[
				'http' =>
				[
					'method' => $method,
					'header' =>
						sprintf("Authorization: DeepL-Auth-Key %s\r\n", $this->key).
						"User-Agent: polywow/test\r\n".
						sprintf("Content-Length: %d\r\n", strlen($query)).
						"Content-Type: application/json\r\n",
					'content' => $query,
					'ignore_errors' => true
				]
			];
			
			return json_decode($this->fetch($path, $context), true);
		}
		
		function get(string $path, string $method, array $data): array
		{
			$query = http_build_query($data);
			$context =
			[
				'http' =>
				[
					'method' => $method,
					'header' =>
						sprintf("Authorization: DeepL-Auth-Key %s\r\n", $this->key).
						"User-Agent: polywow/test\r\n".
						"Content-Type: application/json\r\n",
					'content' => $query,
					'ignore_errors' => true
				]
			];
			
			return json_decode($this->fetch($path, $context), true);
		}
		
		function glossary(string $name): ?array
		{
			$data = [];
			$response = $this->get('/v3/glossaries', 'GET', $data);
			if(array_key_exists('glossaries', $response))
			{
				foreach($response['glossaries'] as $glossary)
					if($glossary['name'] === $name)
						return $glossary;
				
				return null;
			}
			else
				die(implode("\n", $response));
		}
		
		function create(string $name): array
		{
			$data =
			[
				'name' => $name,
				'dictionaries' => []
			];
			$response = $this->put('/v3/glossaries', 'POST', $data);
			if(array_key_exists('glossary_id', $response))
				return $response;
			else
				die(implode("\n", $response));
		}
		
		function edit(array $glossary, string $source, string $target, array ...$entries): void
		{
			$stream = fopen('php://memory', 'r+');
			foreach($entries as $row)
				fputcsv($stream, $row, escape: "\\", eol: "\r\n");
			
			rewind($stream);
			$entries = stream_get_contents($stream);
			$data =
			[
				'dictionaries' =>
				[
					[
						'source_lang' => $source,
						'target_lang' => $target,
						'entries' => $entries,
						'entries_format' => 'csv'
					]
				]
			];
			$response = $this->put('/v3/glossaries/'.$glossary['glossary_id'], 'PATCH', $data);
			if(!array_key_exists('glossary_id', $response))
				die(implode("\n", $response));
		}
		
		function delete(array $glossary): void
		{
			$data = [];
			$this->get('/v3/glossaries/'.$glossary['glossary_id'], 'DELETE', $data);
		}
		
		function translate(array $glossary, string ...$text): array
		{
			$dictionary = array_shift($glossary['dictionaries']);
			$data =
			[
				'text' => $text,
				'target_lang' => $dictionary['target_lang'],
				'source_lang' => $dictionary['source_lang'],
				'context' => 'second person narrative; medieval fantasy; dungeons and dragons; world of warcraft;',
				'split_sentences' => 'nonewlines',
				'preserve_formatting' => true,
				'formality' => 'prefer_less',
				'model_type' => 'quality_optimized',
				'glossary_id' => $glossary['glossary_id'],
				'tag_handling' => 'html'
			];
			$response = $this->put('/v2/translate', 'POST', $data);
			if(array_key_exists('translations', $response))
			{
				return $response['translations'];
			}
			else
			{
				die(implode("\n", $response));
			}
		}
	}
	
	/* UNKNOWN
		+	Cfg_Categories.csv @ patch-2
		WorldSafeLocs.csv @ patch-2
		GMTicketCategory.csv @ patch-1
		Package.csv @ patch-1
		+	PetPersonality.csv @ patch-1
		WowError_Strings.csv @ patch-1
		
		$mapper->assign_dbc('WorldSafeLocs.csv');
		$mapper->assign_dbc('Package.csv');
		$mapper->assign_dbc('PetPersonality.csv');
		$mapper->assign_dbc('WowError_Strings.csv');
	*/
	
	$secret = fopen('.secret', 'r');
	$mapper = new Mapper(fgets($secret), fgets($secret));
	$glossary = new Glossary();
	$translator = new Translator(fgets($secret));
	$locale = Locale::xxYY;
	fclose($secret);
	$mapper->exe_patch('WoW.exe');
	# interface.mpq
	$mapper->assign_lua('GlobalStrings.csv', 'Localization.lua')->knot('globalstrings.db2');
	$mapper->assign_lua('GlueStrings.csv', 'GlueLocalization.lua')->knot('globalstrings.db2');
	# patch-2.mpq / unknown
	$mapper->assign_dbc('Cfg_Categories.csv', $locale, 0, 1);
	# patch-2.mpq
	$mapper->assign_dbc('AreaPOI.csv', $locale, 0)->knot('areapoi.db2', 0)->pair(10, 1)->pair(19, 2);
	$mapper->assign_dbc('AreaTable.csv', $locale, 0)->knot('areatable.db2', 0)->pair(11, 2);
	$mapper->assign_dbc('CreatureType.csv', $locale, 0)->knot('creaturetype.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('EmotesTextData.csv', $locale, 0)->knot('emotestextdata.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('Faction.csv', $locale, 0)->knot('faction.db2', 0)->pair(19, 2)->pair(28, 3);
	$mapper->assign_dbc('ItemSet.csv', $locale, 0)->knot('itemset.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('LFGDungeons.csv', $locale, 0)->knot('lfgdungeons.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('MailTemplate.csv', $locale, 0)->knot('mailtemplate.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('Map.csv', $locale, 0)->knot('map.db2', 0)->pair(4, 2)->pair(20, 3)->pair(29, 4);
	$mapper->assign_dbc('QuestInfo.csv', $locale, 0)->knot('questinfo.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('QuestSort.csv', $locale, 0)->knot('questsort.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('SkillLine.csv', $locale, 0)->knot('skillline.db2', 0)->pair(3, 1)->pair(12, 3);
	$spell = $mapper->assign_dbc('Spell.csv', $locale, 0);
	$spell->knot('spellname.db2', 0)->pair(120, 1);
	$spell->knot('spell.db2', 0)->pair(129, 1)->pair(138, 2)->pair(147, 3);
	$mapper->assign_dbc('SpellFocusObject.csv', $locale, 0)->knot('spellfocusobject.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('SpellItemEnchantment.csv', $locale, 0)->knot('spellitemenchantment.db2', 0)->pair(13, 1);
	$mapper->assign_dbc('SpellMechanic.csv', $locale, 0)->knot('spellmechanic.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('TaxiNodes.csv', $locale, 0)->knot('taxinodes.db2', 0)->pair(5, 1);
	$mapper->assign_dbc('WMOAreaTable.csv', $locale, 0)->knot('wmoareatable.db2', 0)->pair(11, 1);
	$mapper->assign_dbc('WorldStateUI.csv', $locale, 0)->knot('worldstateui.db2', 0)->pair(4, 2)->pair(13, 3)->pair(26, 4);
	# patch-1.mpq / unknown
	$mapper->assign_dbc('GMTicketCategory.csv', $locale, 0);
	$mapper->assign_dbc('NameGen.csv', Locale::enUS, 0);
	# patch-1.mpq
	$mapper->assign_dbc('AuctionHouse.csv', $locale, 0)->knot('auctionhouse.db2', 0)->pair(4, 1);
	$mapper->assign_dbc('ChatChannels.csv', $locale, 0)->knot('chatchannels.db2', 0)->pair(3, 1)->pair(12, 2);
	$mapper->assign_dbc('ChrClasses.csv', $locale, 0)->knot('chrclasses.db2', 0)->pair(5, 1);
	$mapper->assign_dbc('ChrRaces.csv', $locale, 0)->knot('chrraces.db2', 0)->pair(17, 3);
	$mapper->assign_dbc('CreatureFamily.csv', $locale, 0)->knot('creaturefamily.db2', 0)->pair(8, 1);
	$mapper->assign_dbc('Exhaustion.csv', $locale, 0)->knot('exhaustion.db2', 0)->pair(5, 1);
	$mapper->assign_dbc('FactionGroup.csv', $locale, 0)->knot('factiongroup.db2', 0)->pair(3, 2);
	$mapper->assign_dbc('GameTips.csv', $locale, 0)->knot('gametips.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('GMSurveyQuestions.csv', $locale, 0)->knot('gmsurveyquestions.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('ItemBagFamily.csv', $locale, 0)->knot('itembagfamily.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('ItemPetFood.csv', $locale, 0)->knot('itempetfood.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('ItemRandomProperties.csv', $locale, 0)->knot('itemrandomproperties.db2', 0)->pair(7, 1);
	$mapper->assign_dbc('ItemClass.csv', $locale, 0)->knot('itemclass.db2', 2)->pair(3, 1);
	$mapper->assign_dbc('ItemSubClass.csv', $locale, 0, 1)->knot('itemsubclass.db2', 3, 4)->pair(10, 1)->pair(19, 2);
	$mapper->assign_dbc('ItemSubClassMask.csv', $locale, 0, 1)->knot('itemsubclassmask.db2', 2, 3)->pair(2, 1);
	$mapper->assign_dbc('Languages.csv', $locale, 0)->knot('languages.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('LockType.csv', $locale, 0)->knot('locktype.db2', 0)->pair(1, 1)->pair(10, 2)->pair(19, 3);
	$mapper->assign_dbc('PetLoyalty.csv', $locale, 0)->knot('petloyalty.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('Resistances.csv', $locale, 0)->knot('resistances.db2', 0)->pair(3, 1);
	$mapper->assign_dbc('ServerMessages.csv', $locale, 0)->knot('servermessages.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('SkillLineCategory.csv', $locale, 0)->knot('skilllinecategory.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('SpellDispelType.csv', $locale, 0)->knot('spelldispeltype.db2', 0)->pair(1, 1);
	$mapper->assign_dbc('SpellRange.csv', $locale, 0)->knot('spellrange.db2', 0)->pair(4, 1)->pair(13, 2);
	$mapper->assign_dbc('SpellShapeshiftForm.csv', $locale, 0)->knot('spellshapeshiftform.db2', 0)->pair(2, 1);
	$mapper->assign_dbc('Startup_Strings.csv', $locale, 0)->knot('startup_strings.db2', 0)->pair(2, 2);
	$mapper->assign_dbc('TalentTab.csv', $locale, 0)->knot('talenttab.db2', 0)->pair(1, 1);
	# world.sql
	$mapper->assign_sql('areatrigger_teleport', 'id')
		->knot('locales_areatrigger_teleport', 'Entry')
		->pair('status_failed_text', 'Text_loc8');
	$mapper->assign_sql('spell_template', 'Id')
		->knot('spell_template', 'Id')
		->pair('SpellName', 'SpellName8')
		->pair('Rank1', 'Rank8');
	$mapper->assign_sql('creature_template', 'Entry')
		->knot('locales_creature', 'entry')
		->pair('Name', 'name_loc8')
		->pair('SubName', 'subname_loc8');
	$mapper->assign_sql('gameobject_template', 'entry')
		->knot('locales_gameobject', 'entry')
		->pair('name', 'name_loc8');
	$mapper->assign_sql('gossip_menu_option', 'menu_id', 'id')
		->knot('locales_gossip_menu_option', 'menu_id', 'id')
		->pair('option_text', 'option_text_loc8')
		->pair('box_text', 'box_text_loc8');
	$mapper->assign_sql('item_template', 'entry')
		->knot('locales_item', 'entry')
		->pair('name', 'name_loc8')
		->pair('description', 'description_loc8');
	$mapper->assign_sql('npc_text', 'ID')
		->knot('locales_npc_text', 'entry')
		->pair('text0_0', 'Text0_0_loc8')
		->pair('text0_1', 'Text0_1_loc8')
		->pair('text1_0', 'Text1_0_loc8')
		->pair('text1_1', 'Text1_1_loc8')
		->pair('text2_0', 'Text2_0_loc8')
		->pair('text2_1', 'Text2_1_loc8')
		->pair('text3_0', 'Text3_0_loc8')
		->pair('text3_1', 'Text3_1_loc8')
		->pair('text4_0', 'Text4_0_loc8')
		->pair('text4_1', 'Text4_1_loc8')
		->pair('text5_0', 'Text5_0_loc8')
		->pair('text5_1', 'Text5_1_loc8')
		->pair('text6_0', 'Text6_0_loc8')
		->pair('text6_1', 'Text6_1_loc8')
		->pair('text7_0', 'Text7_0_loc8')
		->pair('text7_1', 'Text7_1_loc8');
	$mapper->assign_sql('page_text', 'entry')
		->knot('locales_page_text', 'entry')
		->pair('text', 'Text_loc8');
	$mapper->assign_sql('points_of_interest', 'entry')
		->knot('locales_points_of_interest', 'entry')
		->pair('icon_name', 'icon_name_loc8');
	$mapper->assign_sql('quest_template', 'entry')
		->knot('locales_quest', 'entry')
		->pair('Title', 'Title_loc8')
		->pair('Details', 'Details_loc8')
		->pair('Objectives', 'Objectives_loc8')
		->pair('OfferRewardText', 'OfferRewardText_loc8')
		->pair('RequestItemsText', 'RequestItemsText_loc8')
		->pair('EndText', 'EndText_loc8')
		->pair('ObjectiveText1', 'ObjectiveText1_loc8')
		->pair('ObjectiveText2', 'ObjectiveText2_loc8')
		->pair('ObjectiveText3', 'ObjectiveText3_loc8')
		->pair('ObjectiveText4', 'ObjectiveText4_loc8');
	$mapper->assign_sql('questgiver_greeting', 'Entry', 'Type')
		->knot('locales_questgiver_greeting', 'Entry', 'Type')
		->pair('Text', 'Text_loc8');
	$mapper->assign_sql('trainer_greeting', 'Entry')
		->knot('locales_trainer_greeting', 'Entry')
		->pair('Text', 'Text_loc8');
	$mapper->assign_sql('script_texts', 'entry')
		->knot('script_texts', 'entry')
		->pair('content_default', 'content_loc8');
	$mapper->assign_sql('mangos_string', 'entry')
		->knot('mangos_string', 'entry')
		->pair('content_default', 'content_loc8');
	$mapper->assign_sql('broadcast_text', 'Id')
		->knot('broadcast_text_locale', 'Id')
		->pair('Text', 'Text_lang')
		->pair('Text1', 'Text1_lang');
	# pull everything
	$glossary->data['Rank 2'] = 'Уровень 2';
	$mapper->dump('polywow.sql', 'ruRU', $glossary);
	system('clear');
	$decent_dbc_editor = false;
	$mapper->pull($glossary, $decent_dbc_editor);
	$mapper->dump('polywow.sql', 'ruRU', $glossary);
	$mapper->pare();
	die();
	$remote = $translator->glossary('ruRU');
	if($remote)
		$translator->delete($remote);
	$remote = $translator->create('ruRU');
	$length = 0;
	$entries = [];
	foreach($glossary->useful as $source => $target)
	{
		if(mb_strlen($target) < 2 or preg_match("/[A-z]/", $target))
			continue;
		$length += strlen($source) + strlen($target);
		$entries[] = [$source, $target];
		if($length > 127 * 1024)
		{
			$length = 0;
			$translator->edit($remote, 'en', 'ru', ...$entries);
			$entries = [];
		}
	}
	
	$translator->edit($remote, 'en', 'ru', ...$entries);
?>
