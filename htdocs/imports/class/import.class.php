<?php
/* Copyright (C) 2011       Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2016       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2020		Ahmad Jamaly Rabib	<rabib@metroworks.co.jp>
 * Copyright (C) 2021-2025  Frédéric France		<frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW					<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2026		Alexandre Spangaro	<alexandre@inovea-conseil.com>
 * Copyright (C) 2026 		Juan Pablo Farber	<jpfarber@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/imports/class/import.class.php
 *	\ingroup    import
 *	\brief      File of class to manage imports
 */

/**
 *	Class to manage imports
 */
class Import
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Error codes (or messages)
	 */
	public $errors = array();

	/**
	 * @var string DB Error number
	 */
	public $errno;

	/**
	 * @var array<array{position_of_profile:string,module:DolibarrModules}>
	 */
	public $array_import_module;

	/**
	 * @var int[]
	 */
	public $array_import_perms;

	/**
	 * @var string[]
	 */
	public $array_import_icon;

	/**
	 * @var string[]
	 */
	public $array_import_code;

	/**
	 * @var string[]
	 */
	public $array_import_label;

	/**
	 * @var array<string[]>
	 */
	public $array_import_tables;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_tables_creator;

	/**
	 * @var array<array<string,string>>
	 */
	public $array_import_fields;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_fieldshidden;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_entities;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_regex;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_updatekeys;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_preselected_updatekeys;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_examplevalues;

	/**
	 * @var array<''|array<array{rule:string,file:string,class:string,method:string}>>
	 */
	public $array_import_convertvalue;

	/**
	 * @var array<''|array<string,string>>
	 */
	public $array_import_run_sql_after;

	// To store import templates
	/**
	 * @var int
	 */
	public $id;
	/**
	 * @var string
	 */
	public $hexa; // List of fields in the export profile
	/**
	 * @var string
	 */
	public $datatoimport;

	/**
	 * @var string Name of export profile
	 */
	public $model_name;

	/**
	 * @var int ID
	 */
	public $fk_user;


	/**
	 *    Constructor
	 *
	 *    @param  	DoliDB		$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Load description int this->array_import_module, this->array_import_fields, ... of an importable dataset
	 *
	 *  @param		User	$user      	Object user making import
	 *  @param  	string	$filter		Load a particular dataset only. Index will start to 0.
	 *  @return		int					Return integer <0 if KO, >0 if OK
	 */
	public function load_arrays($user, $filter = '')
	{
		// phpcs:enable
		global $langs, $conf;

		dol_syslog(get_class($this)."::load_arrays user=".$user->id." filter=".$filter);

		$i = 0;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$modulesdir = dolGetModulesDirs();

		// Load list of modules
		foreach ($modulesdir as $dir) {
			$handle = @opendir(dol_osencode($dir));
			if (!is_resource($handle)) {
				continue;
			}

			// Search module files
			while (($file = readdir($handle)) !== false) {
				// Ignore Module Builder backup files (*.php.back)
				if (preg_match('/\.back$/i', $file)) {
					continue;
				}

				if (!preg_match("/^(mod.*)\.class\.php/i", $file, $reg)) {
					continue;
				}

				$modulename = $reg[1];

				// Defined if module is enabled
				$enabled = true;
				$part = strtolower(preg_replace('/^mod/i', '', $modulename));
				// Adds condition for propal module
				if ($part === 'propale') {
					$part = 'propal';
				}
				if (empty($conf->$part->enabled)) {
					$enabled = false;
				}

				if (empty($enabled)) {
					continue;
				}

				// Init load class
				$file = $dir."/".$modulename.".class.php";
				$classname = $modulename;
				require_once $file;
				$module = new $classname($this->db);
				'@phan-var-force DolibarrModules $module';

				if (isset($module->import_code) && is_array($module->import_code)) {
					foreach ($module->import_code as $r => $value) {  // @phan-suppress-current-line PhanTypeMismatchForeach
						if ($filter && ($filter != $module->import_code[$r])) {
							continue;
						}

						// Test if permissions are ok
						/*$perm=$module->import_permission[$r][0];
						//print_r("$perm[0]-$perm[1]-$perm[2]<br>");
						if ($perm[2])
						{
						$bool=$user->rights->{$perm[0]}->{$perm[1]}->{$perm[2]};
						}
						else
						{
						$bool=$user->rights->{$perm[0]}->{$perm[1]};
						}
						if ($perm[0]=='user' && $user->admin) $bool=true;
						//print $bool." $perm[0]"."<br>";
						*/

						// Load lang file
						$langtoload = $module->getLangFilesArray();
						if (is_array($langtoload)) {
							foreach ($langtoload as $key) {
								$langs->load($key);
							}
						}

						// Permission
						$this->array_import_perms[$i] = $user->hasRight('import', 'run');
						// Icon
						$this->array_import_icon[$i] = (isset($module->import_icon[$r]) ? $module->import_icon[$r] : $module->picto);
						// Code of dataset export
						$this->array_import_code[$i] = $module->import_code[$r];
						// Label of dataset export
						$this->array_import_label[$i] = $module->getImportDatasetLabel($r);
						// Array of tables to import (key=alias, value=tablename)
						$this->array_import_tables[$i] = $module->import_tables_array[$r];
						// Array of tables creator field to import (key=alias, value=creator field name)
						$this->array_import_tables_creator[$i] = (isset($module->import_tables_creator_array[$r]) ? $module->import_tables_creator_array[$r] : '');
						// Array of fields to import (key=field, value=label)
						$this->array_import_fields[$i] = (isset($module->import_fields_array[$r]) ? $module->import_fields_array[$r] : []);
						// Array of hidden fields to import (key=field, value=label)
						$this->array_import_fieldshidden[$i] = (isset($module->import_fieldshidden_array[$r]) ? $module->import_fieldshidden_array[$r] : '');
						// Array of entities to export (key=field, value=entity)
						$this->array_import_entities[$i] = (isset($module->import_entities_array[$r]) ? $module->import_entities_array[$r] : '');
						// Array of aliases to export (key=field, value=alias)
						$this->array_import_regex[$i] = (isset($module->import_regex_array[$r]) ? $module->import_regex_array[$r] : '');
						// Array of columns allowed as UPDATE options
						$this->array_import_updatekeys[$i] = (isset($module->import_updatekeys_array[$r]) ? $module->import_updatekeys_array[$r] : '');
						// Array of columns preselected as UPDATE options
						// import_preselected_updatekeys_array does not exist - backward compatibility ?  @phan-suppress-next-line PhanUndeclaredProperty
						$this->array_import_preselected_updatekeys[$i] = (isset($module->import_preselected_updatekeys_array[$r]) ? $module->import_preselected_updatekeys_array[$r] : '');
						// Array of examples
						$this->array_import_examplevalues[$i] = (isset($module->import_examplevalues_array[$r]) ? $module->import_examplevalues_array[$r] : '');
						// Table of conversion rules for a value from another source (key=field, value=array of rules)
						$this->array_import_convertvalue[$i] = (isset($module->import_convertvalue_array[$r]) ? $module->import_convertvalue_array[$r] : '');
						// Sql request to run after import
						$this->array_import_run_sql_after[$i] = (isset($module->import_run_sql_after_array[$r]) ? $module->import_run_sql_after_array[$r] : '');
						// Module
						$this->array_import_module[$i] = array('position_of_profile' => ($module->module_position.'-'.$module->import_code[$r]), 'module' => $module);

						dol_syslog("Import loaded for module ".$modulename." with index ".$i.", dataset=".$module->import_code[$r].", nb of fields=".count($module->import_fields_array[$r]));
						$i++;
					}
				}
			}
			closedir($handle);
		}
		return 1;
	}



	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Detect dictionary tables referenced by import fields.
	 *  Scans example values and regex patterns to find tables that provide
	 *  valid values for foreign-key fields, so they can be included as
	 *  reference sheets in the example file.
	 *
	 *  Detection uses four sources (in order):
	 *   1. Example value text containing: in table "llx_tablename"
	 *   2. Regex patterns of the form: fieldname@llx_tablename
	 *   3. Convert-value rules with a 'table_element' or 'element' key
	 *   4. Heuristic: fields named fk_XXX → tries llx_c_XXX then llx_XXX
	 *
	 *  @param  array<string,string>				$array_import_examplevalues		Example values keyed by field code
	 *  @param  array<string,string>				$array_import_regex				Regex patterns keyed by field code
	 *  @param  array<string,string>				$array_import_fields			Field labels keyed by field code
	 *  @param  array<string,array<string,string>>	$array_import_convertvalue		Convert-value rules keyed by field code
	 *  @return array<string,string>												Array [full_table_name => short_name]
	 */
	public function getRelatedTables($array_import_examplevalues, $array_import_regex, $array_import_fields, $array_import_convertvalue = array())
	{
		// phpcs:enable
		$reftables = array();

		// Source 1: example values containing: in table "llx_tablename"
		foreach ($array_import_examplevalues as $exampleval) {
			preg_match_all('/in table "('.preg_quote(MAIN_DB_PREFIX, '/').'([^"]+))"/', $exampleval, $matches, PREG_SET_ORDER);
			foreach ($matches as $m) {
				if (!isset($reftables[$m[1]])) {
					$reftables[$m[1]] = $m[2];
				}
			}
		}

		// Source 2: regex patterns of the form fieldname@llx_tablename
		foreach ($array_import_regex as $regex) {
			if (preg_match('/^[^@]+@('.preg_quote(MAIN_DB_PREFIX, '/').'([^@]+))$/', $regex, $m)) {
				if (!isset($reftables[$m[1]])) {
					$reftables[$m[1]] = $m[2];
				}
			}
		}

		// Source 3: convert-value rules with explicit 'table_element' or 'element' key
		// e.g. fk_account → element='BankAccount' → llx_bank_account
		foreach ($array_import_convertvalue as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			// Prefer 'table_element' (exact table name without prefix), fall back to 'element'
			$tablekey = '';
			if (!empty($rule['table_element'])) {
				$tablekey = $rule['table_element'];
			} elseif (!empty($rule['element'])) {
				// Convert CamelCase element name to snake_case table name
				// e.g. BankAccount → bank_account
				$tablekey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $rule['element']));
			}
			if ($tablekey) {
				$fulltable = MAIN_DB_PREFIX.$tablekey;
				if (!isset($reftables[$fulltable]) && $this->db->DDLDescTable($fulltable)) {
					$reftables[$fulltable] = $tablekey;
				}
			}
		}

		// Source 4: heuristic for fk_XXX fields not yet resolved
		// Tries llx_c_XXX first (most dictionaries), then llx_XXX
		foreach (array_keys($array_import_fields) as $code) {
			$parts = explode('.', $code, 2);
			if (count($parts) !== 2 || strpos($parts[1], 'fk_') !== 0) {
				continue;
			}
			$fname = substr($parts[1], 3); // strip 'fk_'
			foreach (array(MAIN_DB_PREFIX.'c_'.$fname, MAIN_DB_PREFIX.$fname) as $candidate) {
				if (isset($reftables[$candidate])) {
					break; // already found
				}
				if ($this->db->DDLDescTable($candidate)) {
					$reftables[$candidate] = substr($candidate, strlen(MAIN_DB_PREFIX));
					break;
				}
			}
		}

		return $reftables;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Build an import example file.
	 *  Arrays this->array_export_xxx are already loaded for required datatoexport.
	 *  When $relatedtables is provided and the format supports multiple sheets (xlsx),
	 *  one additional sheet is added per referenced dictionary table so users can
	 *  look up valid values for foreign-key fields directly in the file.
	 *
	 *  @param      string					$model              Name of import engine ('csv', ...)
	 *  @param      string[]				$headerlinefields   Array of values for first line of example file
	 *  @param      string[]				$contentlinevalues	Array of values for content line of example file
	 *  @param		string					$datatoimport		Dataset to import
	 *  @param		array<string,string>	$relatedtables		Optional: [full_table_name => short_name] from getRelatedTables()
	 *  @return		string								        Output string (or empty string for xlsx which writes a file)
	 */
	public function build_example_file($model, $headerlinefields, $contentlinevalues, $datatoimport, $relatedtables = array())
	{
		// phpcs:enable
		global $conf, $langs;

		$indice = 0;

		dol_syslog(get_class($this)."::build_example_file ".$model);

		// Create the import class for the model Import_XXX
		$dir = DOL_DOCUMENT_ROOT."/core/modules/import/";
		$file = "import_".$model.".modules.php";
		$classname = "Import".$model;
		require_once $dir.$file;
		$objmodel = new $classname($this->db, $datatoimport);
		'@phan-var-force ModeleImports $objmodel';

		$outputlangs = $langs; // Lang for output
		$s = '';

		// Generate header
		$s .= $objmodel->write_header_example($outputlangs);

		// Generate title line
		$s .= $objmodel->write_title_example($outputlangs, $headerlinefields);

		// Generate record line
		$s .= $objmodel->write_record_example($outputlangs, $contentlinevalues);

		// Add one sheet per related dictionary table (xlsx only)
		if (!empty($relatedtables) && $model === 'xlsx' && !empty($objmodel->workbook)) {
			foreach ($relatedtables as $fulltable => $shorttable) {
				$resql = $this->db->query("SELECT * FROM ".$fulltable." ORDER BY 1 ASC");
				if (!$resql) {
					continue;
				}
				// Sheet title is limited to 31 chars in Excel
				$sheet = $objmodel->workbook->createSheet();
				$sheet->setTitle(substr($shorttable, 0, 31));

				// Get column names from table structure
				$resqlcols = $this->db->DDLDescTable($fulltable);
				$columns = array();
				if ($resqlcols) {
					while ($objcol = $this->db->fetch_object($resqlcols)) {
						$columns[] = $objcol->Field;
					}
				}

				// Write bold column headers
				$col = 1;
				foreach ($columns as $colname) {
					$sheet->getStyleByColumnAndRow($col, 1)->getFont()->setBold(true);
					$sheet->SetCellValueByColumnAndRow($col, 1, $colname);
					$col++;
				}

				// Write all data rows
				$rownum = 2;
				while ($row = $this->db->fetch_object($resql)) {
					$col = 1;
					foreach ($columns as $colname) {
						$sheet->SetCellValueByColumnAndRow($col, $rownum, $row->$colname);
						$col++;
					}
					$rownum++;
				}

				$this->db->free($resql);
			}
			// Return focus to the main import sheet
			$objmodel->workbook->setActiveSheetIndex(0);
		}

		// Generate footer
		$s .= $objmodel->write_footer_example($outputlangs);

		return $s;
	}

	/**
	 *  Save an import model in database
	 *
	 *  @param		User	$user 	Object user that save
	 *  @return		int				Return integer <0 if KO, >0 if OK
	 */
	public function create($user)
	{
		dol_syslog("Import.class.php::create");

		// Check parameters
		if (empty($this->model_name)) {
			$this->error = 'ErrorWrongParameters';
			return -1;
		}
		if (empty($this->datatoimport)) {
			$this->error = 'ErrorWrongParameters';
			return -1;
		}
		if (empty($this->hexa)) {
			$this->error = 'ErrorWrongParameters';
			return -1;
		}

		$this->db->begin();

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'import_model (';
		$sql .= 'fk_user,';
		$sql .= ' label,';
		$sql .= ' type,';
		$sql .= ' field';
		$sql .= ')';
		$sql .= " VALUES (";
		$sql .= (isset($this->fk_user) ? (int) $this->fk_user : 'null').",";
		$sql .= " '".$this->db->escape($this->model_name)."',";
		$sql .= " '".$this->db->escape($this->datatoimport)."',";
		$sql .= " '".$this->db->escape($this->hexa)."'";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			$this->errno = $this->db->lasterrno();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *  Load an import profil from database
	 *
	 *  @param		int		$id		Id of profil to load
	 *  @return		int				Return integer <0 if KO, >0 if OK
	 */
	public function fetch($id)
	{
		$sql = 'SELECT em.rowid, em.field, em.label, em.type';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'import_model as em';
		$sql .= ' WHERE em.rowid = '.((int) $id);

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$obj = $this->db->fetch_object($result);
			if ($obj) {
				$this->id                   = $obj->rowid;
				$this->hexa                 = $obj->field;
				$this->model_name           = $obj->label;
				$this->datatoimport         = $obj->type;
				$this->fk_user              = $obj->fk_user;
				return 1;
			} else {
				$this->error = "Model not found";
				return -2;
			}
		} else {
			dol_print_error($this->db);
			return -3;
		}
	}

	/**
	 *	Delete object in database
	 *
	 *	@param      User		$user        	User that delete
	 *  @param      int<0,1>	$notrigger	    0=launch triggers after, 1=disable triggers
	 *	@return		int						Return integer <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."import_model";
		$sql .= " WHERE rowid=".((int) $this->id);

		$this->db->begin();

		dol_syslog(get_class($this)."::delete", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error ".$this->db->lasterror();
		}

		/* Not used. This is not a business object. To convert it we must herit from CommonObject
		if (!$error) {
				// Call trigger
				$result=$this->call_trigger('IMPORT_DELETE',$user);
				if ($result < 0) $error++;
				// End call triggers
			}
		}
		*/

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', '.$errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}
}
