<?php
// vim: ft=php foldmarker=<<<,>>>
/*
	Classname:	HDObj
	Purpose:	Generic Heirachical Data Object, without inheritence.
					Can take in xml, and produce a tree-like
					structure of HDObj's. The only rules are:
					1) if an xml element has a text child, then that
						text child's value becomes an attribute of this
						instance of the class
					2) if an xml element has a child which is not just
						text, then that child becomes a new HDObj, and is
						appended onto the children[] array
				can also output the object structure (from itself downward)
				to xml format
				This is basically just a way of getting heirachical data to
				be passed between places, using xml as a transport. This is
				the usage container.
	Depends:	Logger class
				DOMIT! classes
*/
$include_from = dirname(__FILE__);
include_once($include_from.DIRECTORY_SEPARATOR."log.php");

class HDObj extends Logger {
	var $__xml;
	var $__doc;
	var $__root;
	var $__name="hdobj";
	var $DOMIT_INCLUDE="xml_domit_include.php";
	var $DOMIT_DOCUMENT_CLASS="DOMIT_Document";
	
	var $__mvars = array();
	var $__childnames = array();
	var $__attribs = array();
	var $__mvarattribs = array();
	var $__auto_decode = false;
	
	function HDObj($xml = "") {/*<<<*/
		$this->log("constructing");
		if ($xml != "") {
			$this->parse($xml);
		}
	}
/*>>>*/
	function parse($xml = "") {/*<<<*/
		if (!$this->is_xml($xml)) {
			return false;
		}
		$include_from = dirname(__FILE__);
		include_once($include_from.DIRECTORY_SEPARATOR.$this->DOMIT_INCLUDE);
		$this->log("entering parse function");
		$this->clear(); //just in case
		if ($xml != "") {
			$this->__xml = $xml;
		}
		$this->__doc =& new $this->DOMIT_DOCUMENT_CLASS();
		$this->__doc->parser="EXPAT";
		$result = $this->__doc->parseXML($this->__xml);
		if ($result) {
			$this->__root =& $this->__doc->documentElement;
			$this->load_from_domnode($this->__root, $this->__root->nodeName);
		}
		if ($this->__auto_decode) {
			$this->auto_decode();
		}
		return $result;
	}
/*>>>*/
	function auto_decode() { /*<<<*/
		foreach ($this->__mvars as $idx => $val) {
			$this->set_mvar($idx, html_entity_decode($val));
		}
		foreach ($this->__mvarattribs as $mvar => $attarr) {
			foreach ($attarr as $idx => $val) {
				$this->__mvarattribs[$mvar][$idx]=html_entity_decode($val);
			}
		}
		foreach ($this->__attribs as $idx => $val) {
			$this->__attribs[$idx] = html_entity_decode($val);
		}
		foreach ($this->__childnames as $cname) {
			for ($ci = 0; $ci < $this->child_count($cname); $ci++) {
				$this->$cname[$ci]->auto_decode();
			}
		}
	}
/*>>>*/
	function clear() {/*<<<*/
		$this->__xml = "";
		$this->__attribs = array();
		$this->__childnames = array();
		$this->__mvarattribs = array();
		$this->__mvars = array();
	}
/*>>>*/
	function load_from_domnode(&$node, $name="") {/*<<<*/
		$this->log("entering load_from_domnode ($name)");
		if ($name == "") {
			$this->__name = $node->nodeName;
		} else {
			$this->__name = $name;
		}
		if ($node->attributes->getLength()) {
			$this->__attribs = $node->attributes->toArray();
		}
		for ($el =& $node->firstChild; $el; $el =& $el->nextSibling) {
			$cname = $el->nodeName;
			if ($this->is_mvar_node($el)) {
				$textval = $el->firstChild->getText();
				$this->set_mvar($cname, $textval);
				if ($el->attributes->getLength()) {
					$mvar_attribs = $el->attributes->toArray();
					foreach ($mvar_attribs as $idx => $val) {
						$this->set_mvar_attrib($cname, $idx, $val);
					}
				}
			} elseif ($this->is_empty_mvar_node($el)) {
				$this->log($cname." is an empty mvar");
				$this->set_mvar($cname, "");
			} else {
				$this->log("adding new child to $name: $cname");
				$obj =& new HDObj();
				$obj->__doc =& $this->__doc;
				$obj->__root =& $this->__root;
				$obj->__parent =& $this;
				$obj->load_from_domnode($el, $cname);
				$this->append_child($obj);
			}
		}
	}
/*>>>*/
	function is_mvar_node(&$node) {/*<<<*/
		$this->log("checking mvar node on ".$node->nodeName);
		if ($node->hasChildNodes()) {
			$subcount = 0;
			for ($cnode =& $node->firstChild; $cnode; 
				$cnode =& $cnode->nextSibling) {
				$subcount++;
			}
			if ($subcount > 1) return false;
			if ($node->firstChild->nodeType == DOMIT_TEXT_NODE) {
				$this->log($node->nodeName." is an mvar node");
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
/*>>>*/
	function is_empty_mvar_node(&$node) {/*<<<*/
		$this->log("checking for empty mvar node on ".$node->nodeName);
		if ($node->hasChildNodes()) {
			return 0;
		} else {
			$this->log(" -- node has children");
		}
		if ($node->nodeType != DOMIT_TEXT_NODE) {
			// we watch out for a special case: since an mvar is supposed to
			//	be something of the form <mvarname>{value}</mvarname>
			// (where the value can be empty), we allow that when the
			//	node is described in the form <childname attrib="value" />
			// then it is treated as a child, not an mvar
			//if (strpos("</", $node->toNormalizedString()) !== false) {
				return 1;
			//}
		}
		return 0;
	}
/*>>>*/
	function printout($level = 0) {/*<<<*/
		$this->indent($level);
		print($this->__name."<br>");
		foreach ($this->__attribs as $a => $v) {
			$this->indent($level);
			print($a." :: \"".$v."\"<br>");
		}
		foreach ($this->__mvars as $mvar) {
			$this->indent($level + 1);
			print($mvar." => \"".$this->$mvar."\"<br>");
		}
		print("<br>");
		foreach ($this->__childnames as $child) {
			$aobj = $this->$child;
			foreach ($aobj as $obj) {
				$obj->printout($level+1);
			}
		}
	}
/*>>>*/
	function indent($level) {/*<<<*/
		for ($i = 0; $i < $level; $i++) {
			print("&nbsp;&nbsp;&nbsp;&nbsp;");
		}
	}
/*>>>*/
	function toXML($normalize = false, $htmlsafe = false) {/*<<<*/
		$include_from = dirname(__FILE__);
		include_once($include_from.DIRECTORY_SEPARATOR.$this->DOMIT_INCLUDE);
		//creates an xml document with this HDObj as root element
		$this->__doc =& new $this->DOMIT_DOCUMENT_CLASS();
		$this->__root =& $this->__doc->createElement($this->ent($this->__name));
		foreach ($this->__attribs as $aidx => $aval) {
			$this->__root->setAttribute($this->ent($aidx), $this->ent($aval));
		}
		$this->__doc->setDocumentElement($this->__root);
		foreach ($this->__mvars as $mvar) {
			$mel =& $this->__doc->createElement($this->ent($mvar));
			$mel->setText($this->ent($this->$mvar));
			if (array_key_exists($mvar, $this->__mvarattribs)) {
				foreach ($this->__mvarattribs[$mvar] as $attrib => $val) {
					$mel->setAttribute($this->ent($attrib), $this->ent($val));
				}
			}
			$this->__root->appendChild($mel);
		}
		foreach ($this->__childnames as $children) {
			foreach ($this->$children as $idx => $child) {
				$child->__doc =& $this->__doc;
				$child->__root =& $this->__root;
				$child->asDOMel($this->__root);
			}
		}
		if ($normalize) {
			return $this->__doc->toNormalizedString($htmlsafe);
		} else {
			return $this->__doc->toString($htmlsafe);
		}
	}
/*>>>*/
	function asDOMel(&$parent_el) {/*<<<*/
		$el = $this->__doc->createElement($this->ent($this->__name));
		if (is_array($this->__attribs)) {
			foreach ($this->__attribs as $aidx => $aval) {
				$el->setAttribute($this->ent($aidx), $this->ent($aval));
			}
		}
		$parent_el->appendChild($el);
		foreach ($this->__mvars as $mvar) {
			$cel =& $this->__doc->createElement($this->ent($mvar));
			$cel->setText($this->ent($this->$mvar));
			if (array_key_exists($mvar, $this->__mvarattribs)) {
				foreach ($this->__mvarattribs[$mvar] as $attrib => $val) {
					$cel->setAttribute($this->ent($attrib), $this->ent($val));
				}
			}
			$el->appendChild($cel);
		}
		foreach ($this->__childnames as $children) {
			foreach ($this->$children as $idx => $child) {
				$this->log("calling asDOMel on ".serialize(${child})."[${idx}]");
				$child->asDOMel($el);
			}
		}
	}
/*>>>*/
	function ent($str) {/*<<<*/
	// like a limited htmlentities -- so far, just replacing < and >
		return str_replace(">", "&gt;", str_replace("<", "&lt;", $str));
	}
/*>>>*/
	function set_mvars_from_array (&$array) {/*<<<*/
		// useful to set from tabular data
		foreach ($array as $idx => $val) {
			$this->set_mvar($idx, $val);
		}
	}
/*>>>*/
// some functions presented merely for completeness and interface similarity to
//	the Tcl HDObj
	function get_mvar ($mvarname, $default = "") {/*<<<*/
		if (in_array($mvarname, $this->__mvars)) {
			return $this->{$mvarname};
		} else {
			return $default;
		}
	}
/*>>>*/
	function set_mvar ($mvarname, $val) {/*<<<*/
		$this->{$mvarname} = $val;
		if (is_array($this->__mvars) && in_array($mvarname, $this->__mvars))
			return;
		array_push($this->__mvars, $mvarname);
	}
/*>>>*/
	function del_mvar ($mvarname) {/*<<<*/
		if (array_key_exists($mvarname, $this->__mvars)) {
			unset($this->__mvars[$mvarname]);
		}
	}
/*>>>*/
	function set_mvar_attrib ($mvar, $attrib, $val) {/*<<<*/
		$this->__mvarattribs[$mvar][$attrib] = $val;
	}
/*>>>*/
	function get_mvar_attrib ($mvar, $attrib = "", $default = "") {/*<<<*/
		if (array_key_exists($mvar, $this->__mvarattribs)) {
			if ($attrib == "") {
				return $this->__mvarattribs[$mvar];
			} else {
				if (array_key_exists($attrib, $this->__mvarattribs[$mvar])) {
					return $this->__mvarattribs[$mvar][$attrib];
				}
			}
		}
		return $default;
	}
/*>>>*/
	function del_mvar_attrib ($mvar, $attrib) {/*<<<*/
		if (array_key_exists($mvar, $this->__mvarattribs)) {
			if (array_key_exists($attrib, $this->__mvarattribs[$mvar])) {
				unset($this->__mvarattribs[$mvar][$attrib]);
			}
		}
	}
/*>>>*/
	function list_mvars () {/*<<<*/
		return $this->__mvars;
	}
/*>>>*/
	function has_mvar($name) {/*<<<*/
		return (in_array($name, $this->__mvars));
	}
/*>>>*/
	function has_attrib($name) {/*<<<*/
		return (array_key_exists($name, $this->__attribs));
	}
/*>>>*/
	function child_count ($childname="") {/*<<<*/
		if ($childname != "") {
			if (in_array($childname, $this->__childnames)) {
				return count($this->$childname);
			} else {
				$this->log("$childname not in __childnames");
				return 0;
			}
		} else {
			$res = 0;
			foreach ($this->__childnames as $cname) {
				$res += $this->child_count($cname);
			}
			return $res;
		}
	}
/*>>>*/
	function mvar_count() {/*<<<*/
		return count($this->__mvars);
	}
/*>>>*/
	function get_child ($childname, $idx) {/*<<<*/
		// returns reference to child -- don't forget to use =&
		if (in_array($childname, $this->__childnames)) {
			if (array_key_exists($idx, $this->$childname)) {
				// note that the braces around $childname are there as a
				//	workaround for a php inconsistency -- you don't need them
				//	when accessing this item from outside of an object -- just
				//	from the inside. I raised this as a bug (#34069), but
				//	apparently php developers don't see language inconsistencies
				//	as bugs. Nice planet they live on.
				return $this->{$childname}[$idx];
			} else {
				return NULL;
			}
		} else {
			return NULL;
		}
	}
/*>>>*/
	function del_child ($childname, $idx) {/*<<<*/
		if (array_key_exists($childname, $this->__childnames)) {
			if (array_key_exists($idx, $this->$childname)) {
				unset($this->$childnames[$idx]);
			}
		}
	}
/*>>>*/
	function list_childnames () {/*<<<*/
		$ret= array_values($this->__childnames);
		return $ret;
	}
/*>>>*/
	function append_child (&$obj, $make_copy = 0) {/*<<<*/
		if ($make_copy) {
			$copy_varname = uniqid("autovar", true);
			$this->__autovars[$copy_varname] = $obj;
			$cname = $this->__autovars[$copy_varname]->__name;
			if (!is_null($this->__doc))
				$this->__autovars[$copy_varname]->__doc =& $this->__doc;
			if (!is_null($this->__root))
				$this->__autovars[$copy_varname]->__root =& $this->__root;
			$this->__autovars[$copy_varname]->__parent =& $this;
			if (!is_array($this->$cname)) $this->$cname = array();
			array_push($this->$cname, &$this->__autovars[$copy_varname]);
			if (!in_array($cname, $this->__childnames)) {
				array_push($this->__childnames, $cname);
			}
			return $this->__autovars[$copy_varname];
		} else {
			$cname = $obj->__name;
			if (!is_null($this->__doc))
				$obj->__doc =& $this->__doc;
			if (!is_null($this->__root))
				$obj->__root =& $this->__root;
			$obj->__parent =& $this;
			if (!is_array($this->$cname)) $this->$cname = array();
			array_push($this->$cname, &$obj);
			if (!in_array($cname, $this->__childnames)) {
				array_push($this->__childnames, $cname);
			}
			$idx = count($this->$cname) - 1;
			return $this->$cname[$idx];
		}
	}
/*>>>*/
	function create_child($name) {/*<<<*/
		$newchild = HDObj();
		$newchild->__name = $name;
		$this->append_child($newchild);
		return $newchild;
	}
/*>>>*/
	function get_attrib ($attribname, $default = "") {/*<<<*/
		if (array_key_exists($attribname, $this->__attribs)) {
			return $this->__attribs[$attribname];
		} else {
			return $default;
		}
	}
/*>>>*/
	function list_attribs() {/*<<<*/
		return array_keys($this->__attribs);
	}
/*>>>*/
	function set_attrib ($name, $val) {/*<<<*/
		$this->__attribs[$name] = $val;
	}
	/*>>>*/
	function del_attrib ($name) {/*<<<*/
		if (array_key_exists($name, $this->__attribs)) {
			unset($this->__attribs[$name]);
		}
	}
/*>>>*/
	function load_array_copy($arr, $attribnames=array()) {/*<<<*/
		return $this->load_from_array($arr, $attribnames);
	}
/*>>>*/
	function load_from_array(&$arr, $attribnames = array()) {/*<<<*/
		if (!is_array($attribnames)) $attribnames = array();
		foreach ($arr as $idx => $val) {
			if (is_array($val)) {
				$child =& $this->create_child($idx);
				$child->load_from_array($val, $attribnames);
			} else {
				if (in_array($idx, $attribnames)) {
					$this->set_attrib($idx, $val);
				} else {
					$this->set_mvar($idx, $val);
				}
			}
		}
	}
/*>>>*/
	function sort_mvars ($attribname, $asc_or_desc = "asc", $recurse = 1) {/*<<<*/
		foreach ($this->__mvars as $mvarname) {
			if ($this->has_mvar_attrib($mvarname, $attribname)) {
				$sorts[$mvarname] = $this->get_mvar_attrib($mvarname, 
					$attribname);
			} else {
				$leftout[] = $mvarname;
			}
		}
		if (is_array($sorts)) {
			if ($asc_or_desc == "asc") {
				sort($sorts);
			} else {
				rsort($sorts);
			}
			$this->__mvars = $sorts;
			if (is_array($leftout)) {
				foreach ($leftout as $l) {
					$this->__mvars[] = $l;
				}
			}
		} else {
			if (is_array($leftout)) {
				array_splice($this->__mvars, count($this->__mvars),0,$leftout);
			}
		}
		foreach ($this->list_childnames() as $cname) {
			foreach ($this->{$cname} as $child) {
				$child->sort_mvars($attribname, $asc_or_desc, $recurse);
			}
		}
	}
/*>>>*/
	function sort_children($cname,$keyname,$asc_or_desc = "asc",$attrib_or_mvar="", $recurse = 1, $defval="") {/*<<<*/
		if ($this->child_count($cname)) {
			if (($attrib_or_mvar != "attrib") 
					&& ($attrib_or_mvar != "mvar")) {
				// try to auto-detect mvar / attrib
				if ($this->{$cname}[0]->has_attrib($name)) {
					$attrib_or_mvar = "attrib";
				} elseif ($this->{$cname}[0]->has_mvar($name)) {
					$attrib_or_mvar = "mvar";
				} else {
					// we assume attrib, and that some just don't have it
					$attrib_or_mvar = "attrib";
				}
			}
			for ($i = 1; $i < $this->child_count($cname); $i++) {
				$lastchild = $this->child_count($cname);
				for ($j = 1; $j < $lastchild; $j++) {
					if (!is_subclass_of($this->{$cname}[$j], "hdobj") 
						&& !is_a($this->{$cname}[$j], "hdobj")) {
						$this->log("Warning: can't sort child "
							."($cname, $j): is not"
							." an hdobj or descendant");
						continue;
					}
					if (!is_subclass_of($this->{$cname}[$j-1], "hdobj") 
						&& !is_a($this->{$cname}[$j-1], "hdobj")) {
						$num = $j - 1;
						$this->log("Warning: can't sort child "
							."($cname, $num): is not"
							." an hdobj or descendant");
						continue;
					}
					switch ($attrib_or_mvar) {
						case "attrib": {
							if ($asc_or_desc == "asc") {
								if ($this->{$cname}[$j]->get_attrib($keyname)
								< $this->{$cname}[$j-1]->get_attrib($keyname)) {
									$tmp = $this->{$cname}[$j-1];
									$this->{$cname}[$j-1] = $this->{$cname}[$j];
									$this->{$cname}[$j] = $tmp;
								}
							} else {
								if ($this->{$cname}[$j]->get_attrib($keyname)
									> $this->{$cname}[$j-1]->get_attrib($keyname)) {
									$tmp = $this->{$cname}[$j-1];
									$this->{$cname}[$j-1] = $this->{$cname}[$j];
									$this->{$cname}[$j] = $tmp;
								}
							}
							break;
						}
						case "mvar": {
							if ($asc_or_desc == "asc") {
								if ($this->{$cname}[$j]->get_mvar($keyname)
								< $this->{$cname}[$j-1]->get_mvar($keyname)) {
									$tmp = $this->{$cname}[$j-1];
									$this->{$cname}[$j-1] = $this->{$cname}[$j];
									$this->{$cname}[$j] = $tmp;
								}
							} else {
								if ($this->{$cname}[$j]->get_mvar($keyname)
								> $this->{$cname}[$j-1]->get_mvar($keyname)) {
									$tmp = $this->{$cname}[$j-1];
									$this->{$cname}[$j-1] = $this->{$cname}[$j];
									$this->{$cname}[$j] = $tmp;
								}
							}
							break;
						}
					}
				}
				$lastchild--;
			}
		}
		if ($recurse) {
			foreach ($this->list_childnames() as $cname) {
				foreach ($this->{$cname} as $child) {
					$child->sort_children($cname, $keyname, $asc_or_desc,
						$attrib_or_mvar, $recurse, $defval);
				}
			}
		}
	}
	/*>>>*/
	function attrib_to_mvar ($attrib, $propagate = true, $delattrib=false) {/*<<<*/
		// convert an attrib to an mvar on a node
		if ($this->has_attrib($attrib)) {
			$this->set_mvar($attrib, $this->get_attrib($attrib));
			if ($delattrib) {
				$this->del_attrib($attrib);
			}
		}
		if ($propagate) {
			foreach ($this->__childnames as $cname) {
				// oddly enough, you can't do a foreach over the children here,
				//	but you can reference them by number?! When you do a 
				//	foreach, the child exists and works fine within this scope,
				//	but the changes don't stay -- at the point of toXML, the
				//	child has it's old data back... very frikking wierd.
				for ($idx = 0; $idx < $this->child_count($cname); $idx++) {
					$this->{$cname}[$idx]->attrib_to_mvar($attrib, 
						$propagate, $delattrib);
				}
			}
		}
	}
/*>>>*/
	function mvar_to_attrib ($mvar, $propagate = true, $delmvar =false) {/*<<<*/
		// convert an mvar to an attrib on a node
		if ($this->has_mvar($mvar)) {
			$this->set_attrib($mvar, $this->get_mvar($mvar));
		}
		if ($propagate) {
			foreach ($this->__childnames as $cname) {
				for($idx = 0; $idx < $this->child_count($cname); $idx++) {
					$this->{$cname}[$idx]->mvar_to_attrib($mvar, 
						$propagate, $delmvar);
				}
			}
		}
	}
/*>>>*/
	function has_mvar_attrib($mvar, $attrib) {/*<<<*/
		if (array_key_exists($mvar, $this->__mvarattribs)) {
			return (array_key_exists($attrib, $this->__mvarattribs[$mvar]));
		}
		return false;
	}
/*>>>*/
	function collapse_child ($cname, $cindex = 0) {/*<<<*/
		// untested -- to collapse a child into it's parent
		if ($this->child_count($cname)) {
			foreach ($this->{$cname}[$cindex]->__attribs as $attrib => $val) {
				$this->set_attrib($attrib, $val);
			}
			foreach ($this->{$cname}[$cindex]->__mvars as $mvar => $val) {
				$this->set_mvar($mvar, $val);
				if (array_key_exists($mvar, 
					$this->{$cname}[$cindex]->__mvarattribs)) {
					foreach ($this->{$cname}[$cindex]->__mvarattribs[$mvar] 
							as $attrib => $val) {
						$this->set_mvar_attrib($attrib, $val);
					}
				}
			}
			foreach ($this->{$cname}[$cindex]->__childnames as $gcname) {
				for ($gidx = 0; 
					$gidx < $this->{$cname}[$cindex]->child_count($gcname);
						$gidx++) {
				$this->append_child($this->{$cname}[$cindex]->{$gcname}[$gidx]);
				}
			}
			$this->del_child($child);
		}
	}
/*>>>*/
	function is_xml($str) {/*<<<*/
		// simplistic function to determine whether or not a given string
		//	is xml. Not idiot-proof.
		$str = trim($str);
		// super-simple case: empty string
		if (strlen($str) == 0) return false;
		// blindly trust a "<?xml" header
		if (substr($str, 0, 5) == "<?xml") return true; //simple case
		// find first tag
		$len = strlen($str);
		$tag = "";
		$intag = false;
		for ($i = 0; $i < $len; $i++) {
			if (ctype_alnum($str[$i])) {
				if ($intag) {
					$tag.=$str[$i];
				} else return false;
			} else {
				if ($intag) {
					break;
				} else {
					if ($str[$i] == "<") {
						$intag = true;
					} else {
						return false;
					}
				}
			}
		}
		// find matching close tag -- also do a lame check for trailing chars
		$endtag = "</".$tag.">";
		$pos = strpos($str, $endtag);
		if ($pos == ($len - strlen($endtag))) {
			return true;
		} else {
			return false;
		}
	}
/*>>>*/
	function append_xml ($xml, $droproot = 1, $keep_root_attribs = 1) {/*<<<*/
		if (!$this->is_xml($xml)) {
			return false;
		}
		$include_from = dirname(__FILE__);
		include_once($include_from.DIRECTORY_SEPARATOR.$this->DOMIT_INCLUDE);
		$tmpdoc =& new $this->DOMIT_DOCUMENT_CLASS();
		$tmpdoc->parser="EXPAT";
		$result = $tmpdoc->parseXML($xml);
		if (!isset($this->__doc)) {
			// it is possible that this function is called before we have
			//	a document root of our own!
			$this->__doc =& new $this->DOMIT_DOCUMENT_CLASS();
			$this->__doc->parser = "EXPAT";
			$this->__root =& $this->__doc->createElement($this->ent($this->__name));
		}
		if ($result) {
			$tmproot =& $tmpdoc->documentElement;
			$tmpnode = new HDObj();
			$tmpnode->__doc = $tmpdoc;
			$tmpnode->__root = $tmproot;
			$tmpnode->__parent = $this;
			$tmpnode->load_from_domnode($tmproot);
			if ($droproot) {
				foreach ($tmpnode->list_childnames() as $cname) {
					for ($i = 0; $i < $tmpnode->child_count($cname); $i++) {
						$this->append_child($tmpnode->get_child($cname, $i));
					}
				}
				if ($keep_root_attribs) {
					foreach ($tmpnode->list_attribs() as $attrib) {
						if (!$this->has_attrib($attrib)) {
							$this->set_attrib($attrib, 
								$tmpnode->get_attrib($attrib));
						}
					}
				}
			} else {
				$this->append_child($tmpnode);
			}
		}
	}
/*>>>*/
}
?>
