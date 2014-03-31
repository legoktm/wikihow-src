<?php

class Creatives {
	const EXPERIMENT_NAME = 'creatives';

	public static function getImgUrl($img) {
		return('/skins/owl/images/hydra/' . $img);	
	}	
	public static function beforeHeaderDisplay() {
		global $wgUser;
		
        if(Hydra::isEnabled(self::EXPERIMENT_NAME)) 
		{
			if($wgUser->getId() > 0) {
				$sk = $wgUser->getSkin();
				$r=mt_rand(1,4);

				if($r == 1) {
					$sk->addWidget("<a href=\"/Special:CreatePage?utm_source=hydra_creative&utm_campaign=hydra_creative_1\"><img src=\"" . self::getImgUrl('render1.jpg') . "\"></a>", 'hydra_ad');
				}
				elseif($r == 2) {
					$sk->addWidget("<a href=\"/Special:RCPatrol?utm_source=hydra_creative&utm_campaign=hydra_creative_2\"><img src=\"" . self::getImgUrl('render2.jpg') . "\"></a>", 'hydra_ad');
				}
				elseif($r == 3) {
					$sk->addWidget("<a href=\"/Special:ListRequestedTopics?utm_source=hydra_creative&utm_campaign=hydra_creative_3\"><img src=\"" . self::getImgUrl('render3.jpg') . "\"></a>",'hydra_ad');
				}
				elseif($r == 4) {
					$sk->addWidget("<a href=\"/Special:EditFinder/Copyedit?utm_source=hydra_creative&utm_campaign=hydra_creative_4\"><img src=\"" . self::getImgUrl('render4.jpg') . "\"></a>", 'hydra_ad');
				}
			}
		}
		return true;
	}
}
