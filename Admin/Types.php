<?php

namespace Kieran\Bans\Admin\Controller;

use XF\Mvc\ParameterBag;
use Kieran\Support\Entity\TicketType;

class Types extends \XF\Admin\Controller\AbstractController
{

	public function actionIndex(ParameterBag $params)
	{
		return $this->view('Kieran\Bans:BanType', 'kieran_bans_types', ['types' => $this->getTypeRepo()->getTypes()]);
	}

	public function actionEdit(ParameterBag $params)
	{
		$type = $this->assertTypeExists($params['type_id']);
		return $this->typeAddEdit($type);
	}

	public function actionAdd(ParameterBag $params)
	{	
		$type = $this->getTypeRepo()->setupBaseType();
		return $this->typeAddEdit($type);
	}

	protected function typeAddEdit(BanType $type)
	{
		$viewParams = [
			'type' => $type,
			'success' => $this->filter('success', 'bool'),
		];
		return $this->view('Kieran\Bans:BanType\Add', 'kieran_bans_types_edit', $viewParams);
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->type_id)
		{
			$type = $this->assertTypeExists($params->type_id);
		}
		else
		{
			$type = $this->getTypeRepo()->setupBaseType();
		}

		$this->typeSaveProcess($type)->run();

		return $this->redirect($this->buildLink('bans/types'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$type = $this->assertTypeExists($params->type_id);

		if (!$type->canDelete())
		{
			return $this->error(\XF::phrase('type_cannot_be_deleted_associated_with_ban_explain'));
		}

		$type->delete();

		return $this->redirect($this->buildLink('bans/types'));
	}

	protected function typeSaveProcess(TicketType $type)
	{

		$form = $this->formAction();

		$input = $this->filter([
            'type_id' => 'str',
            'name' => 'str',
		]);
		
		$form->basicEntitySave($type, $input);
		$form->run();
		
		return $form;
	}

	protected function assertTypeExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('Kieran\Bans:BanType', $id, $with, $phraseKey);
	}
	
	protected function getTypeRepo()
	{
		return $this->repository('Kieran\Bans:BanType');
	}
}