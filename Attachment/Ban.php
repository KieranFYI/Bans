<?php

namespace Kieran\Bans\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class Ban extends AbstractHandler
{
	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		return $container->canView();
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		return true;
	}

	public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		$container->attach_count--;
		$container->save();
	}

	public function getConstraints(array $context)
	{
		$constraints = \XF::repository('XF:Attachment')->getDefaultAttachmentConstraints();
		$constraints['extensions'][] = 'dem';
		$constraints['extensions'][] = 'mp4';
		$constraints['size'] = 5242880;
		return $constraints;
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['note_id']) ? intval($context['note_id']) : null;
	}

	public function getContainerLink(Entity $container, array $extraParams = [])
	{
		return \XF::app()->router('public')->buildLink('bans', $container, $extraParams);
	}

	public function getContext(Entity $entity = null, array $extraContext = [])
	{

		if ($entity instanceof \Kieran\Bans\Entity\Ban)
		{
			$extraContext['ban_id'] = $entity->ban_id;
		}
		else if ($entity instanceof \Kieran\Bans\Entity\BanNote)
		{
			$extraContext['note_id'] = $entity->note_id;
		}
		else if (!$entity)
		{
			// need nothing
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be ban");
		}

		return $extraContext;
	}
}