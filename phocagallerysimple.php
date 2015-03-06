<?php
/*
 * @package		Joomla.Framework
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @component Phoca Plugin
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later;
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
jimport( 'joomla.plugin.plugin' );

if (!JComponentHelper::isEnabled('com_phocagallery', true)) {
	return JError::raiseError(JText::_('PLG_PHOCAGALLERY_ERROR'), JText::_('PLG_PHOCAGALLERY_COMPONENT_NOT_INSTALLED'));
}

if (! class_exists('PhocaGalleryLoader')) {
    require_once( JPATH_ADMINISTRATOR.DS.'components'.DS.'com_phocagallery'.DS.'libraries'.DS.'loader.php');
}

phocagalleryimport('phocagallery.path.path');
phocagalleryimport('phocagallery.path.route');
phocagalleryimport('phocagallery.library.library');
phocagalleryimport('phocagallery.text.text');
phocagalleryimport('phocagallery.access.access');
phocagalleryimport('phocagallery.file.file');
phocagalleryimport('phocagallery.file.filethumbnail');
phocagalleryimport('phocagallery.image.image');
phocagalleryimport('phocagallery.image.imagefront');
phocagalleryimport('phocagallery.render.renderfront');
phocagalleryimport('phocagallery.render.renderadmin');
phocagalleryimport('phocagallery.ordering.ordering');
phocagalleryimport('phocagallery.picasa.picasa');


class plgContentPhocaGallerySimple extends JPlugin
{	
	var $_plugin_number	= 0;
	
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	public function _setPluginNumber() {
		$this->_plugin_number = (int)$this->_plugin_number + 1;
	}
	
	public function onContentPrepare($context, &$article, &$params, $page = 0) {
	
		$db 		= JFactory::getDBO();
		//$menu 		= &JSite::getMenu();
		$document	= JFactory::getDocument();
		$path 		= PhocaGalleryPath::getPath();
		
		// PARAMS - direct from Phoca Gallery Global configuration
		$component			= 'com_phocagallery';
		$paramsC			= JComponentHelper::getParams($component) ;
		
		$medium_image_width 				= (int)$this->params->get( 'medium_image_width', 100 );
		$medium_image_height 				= (int)$this->params->get( 'medium_image_height', 100 );
		$small_image_width 					= (int)$this->params->get( 'small_image_width', 50 );
		$small_image_height 				= (int)$this->params->get( 'small_image_height', 50 );
		
		
		// Start Plugin
		$regex_one		= '/({pgsimple\s*)(.*?)(})/si';
		$regex_all		= '/{pgsimple\s*.*?}/si';
		$matches 		= array();
		$count_matches	= preg_match_all($regex_all,$article->text,$matches,PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);
		$cssPgPlugin	= '';
		$cssSbox		= '';
		
	// Start if count_matches
	if ($count_matches != 0) {
		
		
		//JHTML::stylesheet('components/com_phocagallery/assets/phocagallery.css' );
	
		for($i = 0; $i < $count_matches; $i++) {
			
			$this->_setPluginNumber();
			
			// Plugin variables
			$view 				= '';
			$catid				= 0;
			$tSize				= 'small';
			$caption			= 3;
			$tMax				= 5;
			$iMax				= 5;
			$close				= 0;
			
			$oT = $oI = $output = $imgDesc = '';
			
			// Get plugin parameters
			$phocagallery	= $matches[0][$i][0];
			preg_match($regex_one,$phocagallery,$phocagallery_parts);
			$parts			= explode("|", $phocagallery_parts[2]);
			$values_replace = array ("/^'/", "/'$/", "/^&#39;/", "/&#39;$/", "/<br \/>/");

			foreach($parts as $key => $value) {
				$values = explode("=", $value, 2);
				
				foreach ($values_replace as $key2 => $values2) {
					$values = preg_replace($values2, '', $values);
				}
				
				// Get plugin parameters from article
					 if($values[0]=='view')				{$view					= $values[1];}
				else if($values[0]=='id')				{$catid					= $values[1];}
				else if($values[0]=='tsize')			{$tSize					= $values[1];}
				else if($values[0]=='caption')			{$caption				= $values[1];}
				else if($values[0]=='close')			{$close					= $values[1];}
				else if($values[0]=='tmax')				{$tMax					= $values[1];}
				else if($values[0]=='imax')				{$iMax					= $values[1];}
			}
			
			
			$max 	= max((int)$iMax, (int)$tMax);
			$limit 	= ' LIMIT 0,'.(int)$max;
			
			$query = 'SELECT cc.id, cc.alias as catalias, a.id, a.catid, a.title, a.alias, a.filename, a.description, a.extm, a.exts, a.extw, a.exth, a.extid, a.extl, a.exto,'
				. ' CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(\':\', cc.id, cc.alias) ELSE cc.id END as catslug, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
				. ' FROM #__phocagallery_categories AS cc'
				. ' LEFT JOIN #__phocagallery AS a ON a.catid = cc.id'
				. ' WHERE a.catid = '.(int) $catid
				. ' AND a.published = 1'
				. ' AND a.approved = 1'
				. ' ORDER BY a.ordering'
				. $limit;
				
				$db->setQuery($query);
				$images = $db->loadObjectList();
				
				if (!$db->query()) {
					$this->setError($db->getErrorMsg());
					return false;
				}
				
				if (!empty($images)) {
				
					// Set position of text
					$textSize	= 16;
					if ($tSize == 'small') {
						$textPosition = (int)$small_image_height - ((int)$textSize + 3);
					} else {
						$textPosition = (int)$medium_image_height - ((int)$textSize + 3);
					}
					$textPositionTxt = $textPosition - 1;
					
					// Add close arrow
					$closeJs = '';
					if ($close == 1) {
						$closeJs = 
'$(\'phocagallerysimpleclickclose'.$this->_plugin_number.'\').addEvent(\'click\', function(event){
     event.stop();
     pGSPSlide.toggle();
	 pGSPSlideWS.toElement(\'phocagallerysimple'.$this->_plugin_number.'\').chain();
   });';
					}
				
					JHTML::_('behavior.framework', true);
					$document->addCustomTag(
'<style type="text/css">
a#phocagallerysimpleclick'.$this->_plugin_number.' {
	text-decoration: none;
}

a#phocagallerysimpleclick'.$this->_plugin_number.':hover,
a#phocagallerysimpleclick'.$this->_plugin_number.':visited,
a#phocagallerysimpleclick'.$this->_plugin_number.':link{
   background:none !important;
}

#phocagallerysimple'.$this->_plugin_number.' .pgspt {
   padding:0px 3px 3px 0px;
   display:block;
   float:left;
}
#phocagallerysimple'.$this->_plugin_number.' .pgspi {
   padding:0px 3px 3px 0px;
   margin: 0px 0px 15px 0px;
}
#phocagallerysimple'.$this->_plugin_number.' .pgspi p {
   margin: 0;padding:0;
}
#phocagallerysimple'.$this->_plugin_number.' .pgspi .pgsptitle {
   font-weight: bold;
}
#phocagallerysimple'.$this->_plugin_number.' .pgspi .pgspdescbox {
   margin-top:3px;
}
#phocagallerysimple'.$this->_plugin_number.' .pgsplink,
#phocagallerysimple'.$this->_plugin_number.' .pgsptxt{
   font-size:'.(int)$textSize.'px;
   font-weight:bold;
   color: #c0c0c0;
   margin-top: '.(int)$textPosition.'px;
   margin-left: 5px;
   display:block;
   float:left;
}
#phocagallerysimple'.$this->_plugin_number.' .pgsptxt {
	margin-top: '.(int)$textPositionTxt.'px;
}
</style>'
					);
					
					$document->addCustomTag(
'<script type="text/javascript">
window.addEvent(\'domready\', function() {

   var pGSPSlideWS = new Fx.Scroll(window);
   var pGSPSlide = new Fx.Slide(\'phocagallerysimplehidden'.$this->_plugin_number.'\').hide();
   var status = {
      \'true\': 1,
      \'false\': 0
   };

   $(\'phocagallerysimpleclick'.$this->_plugin_number.'\').addEvent(\'click\', function(event){
     event.stop();
     pGSPSlide.toggle();
   });
   
   '.$closeJs.'
  
   pGSPSlide.addEvent(\'complete\', function() {
      if (status[pGSPSlide.open] == 1) {
	     $(\'phocagallerysimpleimg'.$this->_plugin_number.'\').setProperty(\'src\', \''.JURI::base(true).'/plugins/content/phocagallerysimple/assets/images/close.png'.'\');
	  } else {
		$(\'phocagallerysimpleimg'.$this->_plugin_number.'\').setProperty(\'src\', \''.JURI::base(true).'/plugins/content/phocagallerysimple/assets/images/open.png'.'\');
	  }
    });
});
</script>'
					);
				
					$i 	= $m	= 0;
					$count 	= count($images);
					foreach ($images as $image) {
					
						if ($image->extw != '') {
							if ($tSize == 'small') {
								$image->linkthubmnailpathT	= $image->exts;
							} else {
								$image->linkthubmnailpathT	= $image->extm;
							}
							$image->linkthubmnailpathI	= $image->extl;
						} else {
							$image->linkthubmnailpathT	= PhocaGalleryImageFront::displayCategoryImageOrNoImage($image->filename, $tSize);
							$image->linkthubmnailpathT	= JURI::base(true).'/'.$image->linkthubmnailpathT;
							$image->linkthubmnailpathI	= PhocaGalleryImageFront::displayCategoryImageOrNoImage($image->filename, 'large');
							$image->linkthubmnailpathI	= JURI::base(true).'/'.$image->linkthubmnailpathI;
						}
						
						if ($i < $tMax) {
							$oT .= '<span class="pgspt"><img src="'.$image->linkthubmnailpathT.'" alt="" /></span>';
						}
						
						if ($i < $iMax) {
							$oI .= '<div class="pgspi"><img src="'.$image->linkthubmnailpathI.'" alt="" />';
							if ($caption == 1) {
								$imgDesc = $image->title;
							} else if ($caption == 2) {
								$imgDesc = $image->description;
							} else if ($caption == 3) {
								$imgDesc = '<span class="pgsptitle">'.$image->title.'</span>';
								if ($imgDesc != '' && $image->description != '') {
									//$imgDesc .= ' - '; Mostly description is stored between p tags
									$imgDesc .= ' ';
								}
								$imgDesc .= $image->description;
							}
							if ($imgDesc != '') {
								$oI .= '<br /><div class="pgspdescbox">'.$imgDesc.'</div>';
							}
							$oI .= '</div>';
							$m++;
						}
						$i++;
					}
				}
				
				if ($oT != '') {
				
					if ($m == 1) {
						$imgText = JText::_('PLG_PHOCAGALLERY_SIMPLE_IMAGE');
					} else if ($m > 1 && $m < 5 ) {
						$imgText = JText::_('PLG_PHOCAGALLERY_SIMPLE_IMAGES_2_4');
					} else {
						$imgText = JText::_('PLG_PHOCAGALLERY_SIMPLE_IMAGES');
					}
					
					
					$output = '<div id="phocagallerysimple'.$this->_plugin_number.'">';
					$output .= '<a id="phocagallerysimpleclick'.$this->_plugin_number.'" href="#">'
						. $oT
						. '<span class="pgsplink">'
						. '<img id="phocagallerysimpleimg'.$this->_plugin_number.'" src="'.JURI::base(true)
						. '/plugins/content/phocagallerysimple/assets/images/open.png" alt="" /></span>'
						. '<span class="pgsptxt">'.$m.' '. $imgText . '</span>'
						. '</a><div style="clear:both;"></div>';
					$output .= '<div id="phocagallerysimplehidden'.$this->_plugin_number.'" >';
					$output .= $oI;
					
					if ($close == 1) {
						$output .= '<div style="float:right;"><a id="phocagallerysimpleclickclose'.$this->_plugin_number.'" href="#">'
							. '<span>'
							. '<img src="'.JURI::base(true)
							. '/plugins/content/phocagallerysimple/assets/images/close.png" alt="" /></span>'
							. '</a></div><div style="clear:both;"></div>';
					}
						
					$output .= '</div>';
					$output .= '</div>';
				}
					
				$article->text = preg_replace($regex_all, $output, $article->text, 1);
			}
			return true;
		}
	}
}
?>