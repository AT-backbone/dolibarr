<?php
/* Copyright (C) 2005-2016  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2007       Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2007-2012  Regis Houssin           <regis.houssin@capnetworks.com>
 * Copyright (C) 2015       Frederic France         <frederic.france@free.fr>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/admin/system/filecheck.php
 *  \brief      Page to check Dolibarr files integrity
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

$langs->load("admin");

if (!$user->admin)
    accessforbidden();

$error=0;


/*
 * View
 */

llxHeader();

print load_fiche_titre($langs->trans("FileCheckDolibarr"),'','title_setup');

print $langs->trans("FileCheckDesc").'<br><br>';

// Version
$var = true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td>'.$langs->trans("Version").'</td><td>'.$langs->trans("Value").'</td></tr>'."\n";
$var = ! $var;
print '<tr '.$bc[$var].'><td width="300">'.$langs->trans("VersionLastInstall").'</td><td>'.$conf->global->MAIN_VERSION_LAST_INSTALL.'</td></tr>'."\n";
$var = ! $var;
print '<tr '.$bc[$var].'><td width="300">'.$langs->trans("VersionLastUpgrade").'</td><td>'.$conf->global->MAIN_VERSION_LAST_UPGRADE.'</td></tr>'."\n";
$var = ! $var;
print '<tr '.$bc[$var].'><td width="300">'.$langs->trans("VersionProgram").'</td><td>'.DOL_VERSION;
// If current version differs from last upgrade
if (empty($conf->global->MAIN_VERSION_LAST_UPGRADE)) {
    // Compare version with last install database version (upgrades never occured)
    if (DOL_VERSION != $conf->global->MAIN_VERSION_LAST_INSTALL)
        print ' '.img_warning($langs->trans("RunningUpdateProcessMayBeRequired",DOL_VERSION,$conf->global->MAIN_VERSION_LAST_INSTALL));
} else {
    // Compare version with last upgrade database version
    if (DOL_VERSION != $conf->global->MAIN_VERSION_LAST_UPGRADE)
        print ' '.img_warning($langs->trans("RunningUpdateProcessMayBeRequired",DOL_VERSION,$conf->global->MAIN_VERSION_LAST_UPGRADE));
}
print '</td></tr>'."\n";
print '</table>';
print '<br>';


// Modified or missing files
$file_list = array('missing' => array(), 'updated' => array());

// Local file to compare to
$xmlshortfile = GETPOST('xmlshortfile')?GETPOST('xmlshortfile'):'/install/filelist-'.DOL_VERSION.'.xml';
$xmlfile = DOL_DOCUMENT_ROOT.$xmlshortfile;
// Remote file to compare to
$xmlremote = GETPOST('xmlremote');
if (empty($xmlremote) && ! empty($conf->global->MAIN_FILECHECK_URL)) $xmlremote = $conf->global->MAIN_FILECHECK_URL;
$param='MAIN_FILECHECK_URL_'.DOL_VERSION;
if (empty($xmlremote) && ! empty($conf->global->$param)) $xmlremote = $conf->global->$param;
if (empty($xmlremote)) $xmlremote = 'https://www.dolibarr.org/files/stable/signatures/filelist-'.DOL_VERSION.'.xml';


// Test if remote test is ok
$enableremotecheck = True;
if (preg_match('/beta|alpha|rc/i', DOL_VERSION) || ! empty($conf->global->MAIN_ALLOW_INTEGRITY_CHECK_ON_UNSTABLE)) $enableremotecheck=False;
$enableremotecheck = true;

print '<form name="check" action="'.$_SERVER["PHP_SELF"].'">';
print $langs->trans("MakeIntegrityAnalysisFrom").':<br>';
print '<!-- for a local check target=local&xmlshortfile=... -->'."\n";
if (dol_is_file($xmlfile))
{
    print '<input type="radio" name="target" value="local"'.((! GETPOST('target') || GETPOST('target') == 'local') ? 'checked="checked"':'').'"> '.$langs->trans("LocalSignature").' = ';
    print '<input name="xmlshortfile" class="flat minwidth200" value="'.dol_escape_htmltag($xmlshortfile).'">';
    print '<br>';
}
else
{
    print '<input type="radio" name="target" value="local"> '.$langs->trans("LocalSignature").' = ';
    print '<input name="xmlshortfile" class="flat minwidth200" value="'.dol_escape_htmltag($xmlshortfile).'">';
    print ' <span class="warning">('.$langs->trans("AvailableOnlyOnPackagedVersions").')</span>';
    print '<br>';
}
print '<!-- for a remote target=remote&xmlremote=... -->'."\n";
if ($enableremotecheck)
{
    print '<input type="radio" name="target" value="remote"'.(GETPOST('target') == 'remote' ? 'checked="checked"':'').'> '.$langs->trans("RemoteSignature").' = ';
    print '<input name="xmlremote" class="flat quatrevingtpercent" value="'.dol_escape_htmltag($xmlremote).'"><br>';
}
else
{
    print '<input type="radio" name="target" value="remote" disabled="disabled"> '.$langs->trans("RemoteSignature").' = '.$xmlremote;
    if (! GETPOST('xmlremote')) print ' <span class="warning">('.$langs->trans("FeatureAvailableOnlyOnStable").')</span>';
    print '<br>';
}
print '<br><div class="center"><input type="submit" name="check" class="button" value="'.$langs->trans("Check").'"></div>';
print '</form>';
print '<br>';
print '<br>';

if (GETPOST('target') == 'local')
{
    if (dol_is_file($xmlfile))
    {
        $xml = simplexml_load_file($xmlfile);
    }
    else
    {
        print $langs->trans('XmlNotFound') . ': ' . $xmlfile;
        $error++;
    }
}
if (GETPOST('target') == 'remote')
{
    $xmlarray = getURLContent($xmlremote);
    
    // Return array('content'=>response,'curl_error_no'=>errno,'curl_error_msg'=>errmsg...)
    if (! $xmlarray['curl_error_no'] && $xmlarray['http_code'] != '404')
    {
        $xmlfile = $xmlarray['content'];
        //print "eee".$xmlfile."eee";
        $xml = simplexml_load_string($xmlfile);
    }
    else
    {
        $errormsg=$langs->trans('XmlNotFound') . ': ' . $xmlremote.' - '.$xmlarray['http_code'].' '.$xmlarray['curl_error_no'].' '.$xmlarray['curl_error_msg'];
        setEventMessages($errormsg, null, 'errors');
        $error++;
    }
}       
        

if ($xml)
{
    $checksumconcat = array();
    $file_list = array();
    $out = '';
    
    // Forced constants
    if (is_object($xml->dolibarr_constants[0]))
    {
        $out.=load_fiche_titre($langs->trans("ForcedConstants"));
        
        $out.='<table class="noborder">';
        $out.='<tr class="liste_titre">';
        $out.='<td>#</td>';
        $out.='<td>' . $langs->trans("Constant") . '</td>';
        $out.='<td align="center">' . $langs->trans("ExpectedValue") . '</td>';
        $out.='<td align="center">' . $langs->trans("Value") . '</td>';
        $out.='</tr>'."\n";
        $var = true;

        $i = 0;
        foreach ($xml->dolibarr_constants[0]->constant as $constant)    // $constant is a simpleXMLElement
        {
            $constname=$constant['name'];
            $constvalue=(string) $constant;
            $constvalue = (empty($constvalue)?'0':$constvalue);
            // Value found                
            $value='';
            if ($constname && $conf->global->$constname != '') $value=$conf->global->$constname;
            $valueforchecksum=(empty($value)?'0':$value);
            
            $checksumconcat[]=$valueforchecksum;
            
            $i++;
            $var = !$var;
            $out.='<tr ' . $bc[$var] . '>';
            $out.='<td>'.$i.'</td>' . "\n";
            $out.='<td>'.$constname.'</td>' . "\n";
            $out.='<td align="center">'.$constvalue.'</td>' . "\n";
            $out.='<td align="center">'.$valueforchecksum.'</td>' . "\n";
            $out.="</tr>\n";
        }

        if ($i==0)
        {
            $out.='<tr ' . $bc[false] . '><td colspan="4" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
        }
        $out.='</table>';
        
        $out.='<br>';
    }
    
    // Scan htdocs
    if (is_object($xml->dolibarr_htdocs_dir[0]))
    {
        //var_dump($xml->dolibarr_htdocs_dir[0]['includecustom']);exit;
        $includecustom=(empty($xml->dolibarr_htdocs_dir[0]['includecustom'])?0:$xml->dolibarr_htdocs_dir[0]['includecustom']);

        // Defined qualified files (must be same than into generate_filelist_xml.php)
        $regextoinclude='\.(php|css|html|js|json|tpl|jpg|png|gif|sql|lang)$';
        $regextoexclude='('.($includecustom?'':'custom|').'documents|conf|install)$';  // Exclude dirs
        $scanfiles = dol_dir_list(DOL_DOCUMENT_ROOT, 'files', 1, $regextoinclude, $regextoexclude);

        // Fill file_list with files in signature, new files, modified files
        $ret = getFilesUpdated($file_list, $xml->dolibarr_htdocs_dir[0], '', DOL_DOCUMENT_ROOT, $checksumconcat, $scanfiles);		// Fill array $file_list
        // Complete with list of new files
        foreach ($scanfiles as $keyfile => $valfile)
        {
            $tmprelativefilename=preg_replace('/^'.preg_quote(DOL_DOCUMENT_ROOT,'/').'/','', $valfile['fullname']);
            if (! in_array($tmprelativefilename, $file_list['insignature']))
            {
                $md5newfile=@md5_file($valfile['fullname']);    // Can fails if we don't have permission to open/read file
                $file_list['added'][]=array('filename'=>$tmprelativefilename, 'md5'=>$md5newfile);
            }
        }
        
        // Files missings
        $out.=load_fiche_titre($langs->trans("FilesMissing"));
        
        $out.='<table class="noborder">';
        $out.='<tr class="liste_titre">';
        $out.='<td>#</td>';
        $out.='<td>' . $langs->trans("Filename") . '</td>';
        $out.='<td align="center">' . $langs->trans("ExpectedChecksum") . '</td>';
        $out.='</tr>'."\n";
        $var = true;
        $tmpfilelist = dol_sort_array($file_list['missing'], 'filename');
        if (is_array($tmpfilelist) && count($tmpfilelist))
        {
            $i = 0;
	        foreach ($tmpfilelist as $file)
	        {
	            $i++;
	            $var = !$var;
	            $out.='<tr ' . $bc[$var] . '>';
	            $out.='<td>'.$i.'</td>' . "\n";
	            $out.='<td>'.$file['filename'].'</td>' . "\n";
	            $out.='<td align="center">'.$file['expectedmd5'].'</td>' . "\n";
	            $out.="</tr>\n";
	        }
        }
        else 
        {
            $out.='<tr ' . $bc[false] . '><td colspan="3" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
        }            
        $out.='</table>';

        $out.='<br>';

        // Files modified
        $out.=load_fiche_titre($langs->trans("FilesModified"));
        
        $totalsize=0;
        $out.='<table class="noborder">';
        $out.='<tr class="liste_titre">';
        $out.='<td>#</td>';
        $out.='<td>' . $langs->trans("Filename") . '</td>';
        $out.='<td align="center">' . $langs->trans("ExpectedChecksum") . '</td>';
        $out.='<td align="center">' . $langs->trans("CurrentChecksum") . '</td>';
        $out.='<td align="right">' . $langs->trans("Size") . '</td>';
        $out.='<td align="right">' . $langs->trans("DateModification") . '</td>';
        $out.='</tr>'."\n";
        $var = true;
        $tmpfilelist2 = dol_sort_array($file_list['updated'], 'filename');
        if (is_array($tmpfilelist2) && count($tmpfilelist2))
        {
            $i = 0;
	        foreach ($tmpfilelist2 as $file)
	        {
	            $i++;
	            $var = !$var;
	            $out.='<tr ' . $bc[$var] . '>';
	            $out.='<td>'.$i.'</td>' . "\n";
	            $out.='<td>'.$file['filename'].'</td>' . "\n";
	            $out.='<td align="center">'.$file['expectedmd5'].'</td>' . "\n";
	            $out.='<td align="center">'.$file['md5'].'</td>' . "\n";
	            $size = dol_filesize(DOL_DOCUMENT_ROOT.'/'.$file['filename']);
	            $totalsize += $size;
	            $out.='<td align="right">'.dol_print_size($size).'</td>' . "\n";
	            $out.='<td align="right">'.dol_print_date(dol_filemtime(DOL_DOCUMENT_ROOT.'/'.$file['filename']),'dayhour').'</td>' . "\n";
	            $out.="</tr>\n";
	        }
            $out.='<tr class="liste_total">';
            $out.='<td></td>' . "\n";
            $out.='<td>'.$langs->trans("Total").'</td>' . "\n";
            $out.='<td align="center"></td>' . "\n";
            $out.='<td align="center"></td>' . "\n";
            $out.='<td align="right">'.dol_print_size($totalsize).'</td>' . "\n";
            $out.='<td align="right"></td>' . "\n";
            $out.="</tr>\n";
        }
        else 
        {
            $out.='<tr ' . $bc[false] . '><td colspan="5" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
        }
        $out.='</table>';
        
        $out.='<br>';
        
        // Files added
        $out.=load_fiche_titre($langs->trans("FilesAdded"));
        
        $totalsize = 0;
        $out.='<table class="noborder">';
        $out.='<tr class="liste_titre">';
        $out.='<td>#</td>';
        $out.='<td>' . $langs->trans("Filename") . '</td>';
        $out.='<td align="center">' . $langs->trans("ExpectedChecksum") . '</td>';
        $out.='<td align="center">' . $langs->trans("CurrentChecksum") . '</td>';
        $out.='<td align="right">' . $langs->trans("Size") . '</td>';
        $out.='<td align="right">' . $langs->trans("DateModification") . '</td>';
        $out.='</tr>'."\n";
        $var = true;
        $tmpfilelist3 = dol_sort_array($file_list['added'], 'filename');
        if (is_array($tmpfilelist3) && count($tmpfilelist3))
        {
            $i = 0;
            foreach ($tmpfilelist3 as $file)
            {
                $i++;
                $var = !$var;
                $out.='<tr ' . $bc[$var] . '>';
                $out.='<td>'.$i.'</td>' . "\n";
                $out.='<td>'.$file['filename'].'</td>' . "\n";
                $out.='<td align="center">'.$file['expectedmd5'].'</td>' . "\n";
                $out.='<td align="center">'.$file['md5'].'</td>' . "\n";
                $size = dol_filesize(DOL_DOCUMENT_ROOT.'/'.$file['filename']);
                $totalsize += $size;
                $out.='<td align="right">'.dol_print_size($size).'</td>' . "\n";
                $out.='<td align="right">'.dol_print_date(dol_filemtime(DOL_DOCUMENT_ROOT.'/'.$file['filename']),'dayhour').'</td>' . "\n";
                $out.="</tr>\n";
            }
            $out.='<tr class="liste_total">';
            $out.='<td></td>' . "\n";
            $out.='<td>'.$langs->trans("Total").'</td>' . "\n";
            $out.='<td align="center"></td>' . "\n";
            $out.='<td align="center"></td>' . "\n";
            $out.='<td align="right">'.dol_print_size($totalsize).'</td>' . "\n";
            $out.='<td align="right"></td>' . "\n";
            $out.="</tr>\n";
        }
        else
        {
            $out.='<tr ' . $bc[false] . '><td colspan="5" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
        }
        $out.='</table>';
        
     
        // Show warning
        if (empty($tmpfilelist) && empty($tmpfilelist2) && empty($tmpfilelist3))
        {
            setEventMessage($langs->trans("FileIntegrityIsStrictlyConformedWithReference"));
        }
        else
        {
            setEventMessage($langs->trans("FileIntegritySomeFilesWereRemovedOrModified"), 'warnings');
        }        
    }
    else
    {
        print 'Error: Failed to found dolibarr_htdocs_dir into XML file '.$xmlfile;
        $error++;
    }


    // Scan scripts
    /*
    if (is_object($xml->dolibarr_script_dir[0]))
    {
        $file_list = array();
        $ret = getFilesUpdated($file_list, $xml->dolibarr_htdocs_dir[0], '', ???, $checksumconcat);		// Fill array $file_list
    }*/
    
    
    asort($checksumconcat); // Sort list of checksum        
    //var_dump($checksumconcat);
    $checksumget = md5(join(',',$checksumconcat));
    $checksumtoget = trim((string) $xml->dolibarr_htdocs_dir_checksum);

    /*var_dump(count($file_list['added']));
    var_dump($checksumget);
    var_dump($checksumtoget);
    var_dump($checksumget == $checksumtoget);*/
    print_fiche_titre($langs->trans("GlobalChecksum")).'<br>';
    print $langs->trans("ExpectedChecksum").' = '. ($checksumtoget ? $checksumtoget : $langs->trans("Unknown")) .'<br>';
    print $langs->trans("CurrentChecksum").' = ';
    if ($checksumget == $checksumtoget)
    {
        if (count($file_list['added'])) print $checksumget.' - <span class="warning">'.$langs->trans("FileIntegrityIsOkButFilesWereAdded").'</span>';
        else print '<span class="ok">'.$checksumget.'</span>';
    }
    else
    {
        print '<span class="error">'.$checksumget.'</span>';
    }
    
    print '<br>';
    print '<br>';
    
    // Output detail
    print $out;
}




llxFooter();

$db->close();

exit($error);


/**
 * Function to get list of updated or modified files.
 * $file_list is used as global variable
 *
 * @param	array				$file_list	        Array for response
 * @param   SimpleXMLElement	$dir    	        SimpleXMLElement of files to test
 * @param   string   			$path   	        Path of files relative to $pathref. We start with ''. Used by recursive calls.
 * @param   string              $pathref            Path ref (DOL_DOCUMENT_ROOT)
 * @param   array               $checksumconcat     Array of checksum
 * @return  array               			        Array of filenames
 */
function getFilesUpdated(&$file_list, SimpleXMLElement $dir, $path = '', $pathref = '', &$checksumconcat = array())
{
    $exclude = 'install';

    foreach ($dir->md5file as $file)    // $file is a simpleXMLElement
    {
        $filename = $path.$file['name'];
        $file_list['insignature'][] = $filename;
        
        //if (preg_match('#'.$exclude.'#', $filename)) continue;

        if (!file_exists($pathref.'/'.$filename))
        {
            $file_list['missing'][] = array('filename'=>$filename, 'expectedmd5'=>(string) $file);
        }
        else
		{
            $md5_local = md5_file($pathref.'/'.$filename);
            if ($md5_local != (string) $file) $file_list['updated'][] = array('filename'=>$filename, 'expectedmd5'=>(string) $file, 'md5'=>(string) $md5_local);
            $checksumconcat[] = $md5_local;
		}
    }

    foreach ($dir->dir as $subdir) getFilesUpdated($file_list, $subdir, $path.$subdir['name'].'/', $pathref, $checksumconcat);

    return $file_list;
}

