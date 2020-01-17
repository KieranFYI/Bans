<?php

namespace Kieran\Bans\Pub\Controller;

use XF\Mvc\ParameterBag;

use Kieran\Bans\Repository\Ban as BanRepo;

class Bans extends \XF\Pub\Controller\AbstractController
{

	public function actionIndex(ParameterBag $params) {

		if ($params->ban_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}
		
		$viewParams = [
			'canCreate' => $this->canCreate(),
			'canViewBans' => $this->canView(),
			'canManage' => $this->canManage(),
			'bans' => $this->getBanRepo()->find([
				'banned_uid' => $this->getSteamIDs(),
			])
		];

		return $this->view('Kieran\Bans:MyBans', 'kieran_bans_mybans', $viewParams);
	}

	public function actionCreated(ParameterBag $params) {

		if (!$this->canView()) {
			return $this->noPermission();
		}

		$page = $this->filterPage();
		$perPage = 25;

		$filters = $this->getFilterInput();
		$filters['admin_user_id'] = \XF::visitor()->user_id;
		$finder = $this->getBanRepo()->findPaged($filters, $page, $perPage);
		$total = $finder->total();

		$viewParams = [
			'canCreate' => $this->canCreate(),
			'canViewBans' => $this->canView(),
			'canManage' => $this->canManage(),
			'bans' => $finder->fetch(),
			'perPage' => $perPage,
			'total' => $total,
			'page' => $page,
		];

		return $this->view('Kieran\Bans:MyBans', 'kieran_bans_created', $viewParams);
	}

	public function actionManage(ParameterBag $params) {

		if (!$this->canView()) {
			return $this->noPermission();
		}

		$page = $this->filterPage();
		$perPage = 25;

		$filters = $this->getFilterInput();
		$finder = $this->getBanRepo()->findPaged($filters, $page, $perPage);
		$total = $finder->total();

		$viewParams = [
			'canCreate' => $this->canCreate(),
			'canViewBans' => $this->canView(),
			'canManage' => $this->canManage(),
			'bans' => $finder->fetch(),
			'filters' => $filters,
			'perPage' => $perPage,
			'total' => $total,
			'page' => $page,
		];

		return $this->view('Kieran\Bans:MyBans', 'kieran_bans_manage', $viewParams);
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if (!$this->canCreate()) {
			return $this->noPermission();
		}
        
        $values = [];

		if ($params->ban_id)
		{
			$ban = $this->assertBanExists($params->ban_id);

			if ($ban->remaining_time < 0) {
				return $this->noPermission();
			}

            $values = [
                'Target UID' => $ban->steamid32,
                'Target IP' => $ban->target_ip,
                'Target Name' => $ban->target_name,
                'Ban Length' => $ban->getLength(),
                'Ban Reason' => $ban->ban_reason,
                'Ban Type' => $ban->Type->name,
            ];
		}
		else
		{
			$ban = $this->getBanRepo()->setupBaseBan();
		}

		$createFirst = $ban->isInsert();

		$this->banSaveProcess($ban)->run();

		if ($createFirst) {
			$message = $this->plugin('XF:Editor')->fromInput('message');
			$ban->createFirst($message);
		} else {
			$data = [
				'Target UID' => $ban->steamid32,
				'Target IP' => $ban->target_ip,
				'Target Name' => $ban->target_name,
				'Ban Length' => $ban->getLength(),
				'Ban Reason' => $ban->ban_reason,
				'Ban Type' => $ban->Type->name,
			];

			foreach ($values as $key => $value) {
				if ($data[$key] == $value) {
					unset($data[$key]);
				}
			}

			if (count($data)) {
				$note = $ban->newNote();
				$note->data = $data;
				$note->save();
			}
		}

		return $this->redirect($this->buildLink('bans', $ban));
	}

	public function actionReview(ParameterBag $params) {

		$ban = $this->assertBanExists($params->ban_id);

		if (!$this->canCreate() || $ban->remaining_time < 0) {
			return $this->noPermission();
		}

		$input = $this->filter('ban_review', 'uint');

		if ($this->filter('apply', 'bool'))
		{
			if ($ban->ban_review != $input) {

				$message = trim($this->plugin('XF:Editor')->fromInput('message'));
				if (!strlen($message)) {
					throw $this->exception($this->notFound(\XF::phrase('kieran_bans_message_required')));
				}

				$ban->ban_review = $input;
				$ban->save();
				$data = ['Review Status' => $ban->getStatus()];

				if ($ban->ban_review == 3) {
					$ban->ban_status = 1;
					$ban->save();
					$data['Ban Status'] = 'Lifted';
				}

				$note = $ban->newNote();
				$note->data = $data;
				$note->note = $message;
				$note->save();
			}
			return $this->redirect($this->router()->buildLink('bans', $ban));
		}
		else
		{
			$viewParams = [
				'ban' => $ban,
				'canViewBans' => $this->canView(),
				'canManage' => $this->canManage(),
				'statuses' => BanRepo::getStatuses(),
			];

			return $this->view('Kieran\Bans:Bans\Status', 'kieran_bans_review', $viewParams);
		}
	}

	public function actionFilters(ParameterBag $params)
	{
		$filters = $this->getFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink('bans/manage', null, $filters));
		}
		else if ($this->request->isXhr())
		{
			
			$viewParams = [
				'filters' => $filters,
				'types' => $this->getBanTypeRepo()->getTypes(),
				'statuses' => BanRepo::getStatuses(),
			];

			return $this->view('Kieran\Bans:Ban\Filters', 'kieran_bans_filters', $viewParams);
		}
		else
		{
			return $this->redirect($this->router()->buildLink('bans/manage'));
		}
	}

	protected function getFilterInput() {

		$input = $this->filter([
			'admin_user' => 'str',
			'admin_user_id' => 'uint',
			'banned_uid' => 'str',
			'status' => 'array',
			'type' => 'array',
			'date' => 'array',
			'order' => 'str',
			'direction' => 'str',
		]);

		if ($input['admin_user_id']) {
			$filters['admin_user_id'] = $input['admin_user_id'];
		} else if ($input['admin_user']) {
			$user = $this->em()->findOne('XF:User', ['username' => $input['admin_user']]);
			if ($user) {
				$filters['admin_user_id'] = $user->user_id;
			}
		}

		if ($input['banned_uid']) {
			$filters['banned_uid'] = $this->toSteam64($input['banned_uid']);
		}

		if ($input['status']) {
			$filters['status'] = $input['status'];
		}

		if ($input['type']) {
			$filters['type'] = $input['type'];
		}


		$sorts = BanRepo::getAvailableSorts();
		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			$filters['order'] = $input['order'];
			$filters['direction'] = $input['direction'];
		} else {
			$filters['order'] = 'timestamp';
			$filters['direction'] = 'desc';
		}

		if (isset($input['date'])) {
			$filters['date'] = $input['date'];
		}

		return $filters;
	}

	public function banSaveProcess($ban) {
		$form = $this->formAction();

		$input = $this->filter([
			'ban' => [
				'target_name' => 'str',
				'target_id' => 'str',
				'target_ip' => 'str',
				'ban_reason' => 'str',
				'type_id' => 'str',
				'ban_length' => 'uint',
			]
		]);
		$input['ban']['target_id'] = $this->toSteam64($input['ban']['target_id']);

		$form->basicEntitySave($ban, $input['ban']);

		$visitor = \XF::visitor();
		$form->basicEntitySave($ban, [
			'admin_name' => $visitor->username,
			'admin_id' => $this->getPrimarySteamID(),
			'admin_user_id' => $visitor->user_id,
			'admin_ip' => $this->app()->request()->getIp(),
		]);

		return $form;
	}

	protected function banCreateEdit(\Kieran\Bans\Entity\Ban $ban)
	{
		$viewParams = [
			'ban' => $ban,
			'success' => $this->filter('success', 'bool'),
			'canViewBans' => $this->canView(),
			'canManage' => $this->canManage(),
			'times' => BanRepo::getBanTimes(),
			'types' => $this->getBanTypeRepo()->getTypes(),
		];

		if (!$ban->isInsert()) {
			$viewParams['attachmentData'] = $this->getReplyAttachmentData($ban);
		}
		return $this->view('Kieran\Bans:Ban\Edit', 'kieran_bans_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		
		$ban = $this->assertBanExists($params->ban_id);
		
		if (!$this->canCreate() || $ban->remaining_time < 0) {
			return $this->noPermission();
		}

		return $this->banCreateEdit($ban);
	}

	public function actionCreate(ParameterBag $params)
	{	
		if (!$this->canCreate()) {
			return $this->noPermission();
		}

		$ban = $this->getBanRepo()->setupBaseBan();

		return $this->banCreateEdit($ban);
	}

	public function actionReply(ParameterBag $params)
	{
		$ban = $this->assertBanExists($params->ban_id);

		if (!$this->canManage() && $ban->user_id != \XF::visitor()->user_id) {
			return $this->noPermission();
		}

		$message = $this->plugin('XF:Editor')->fromInput('message');
		if (strlen($message)) {
			$leadership = $this->filter('leadership', 'bool');
			if (!$this->canManage()) {
				$leadership = false;
			}

			$note = $ban->newNote();
			$note->note = $message;
			$note->visible = $leadership ? 0 : 1;
			$note->save();

			$note->associateAttachments($this->filter('attachment_hash', 'str'));
		} else {
			throw $this->exception($this->notFound(\XF::phrase('kieran_bans_message_required')));
		}

		return $this->redirect($this->router()->buildLink('bans', $ban));
	}

	public function actionView(ParameterBag $params) {
		$ban = $this->assertBanExists($params->ban_id);

		if (!$this->canView()) {
			return $this->noPermission();
		}

		if (!count($ban->Notes)) {
			$ban->createFirst();
		}

		if ($ban->admin_user_id != \XF::visitor()->user_id && !$this->canView()) {
			return $this->noPermission();
		}

		$viewParams = [
			'ban' => $ban,
			'canViewBans' => $this->canView(),
			'canCreate' => $this->canCreate(),
			'canManage' => $this->canManage(),
		];

		if (!$ban->isInsert()) {
			$viewParams['attachmentData'] = $this->getReplyAttachmentData($ban);
		}
		return $this->view('Kieran\Bans:Ban\View', 'kieran_bans_view', $viewParams);

	}

	public function actionLift(ParameterBag $params) {

		$ban = $this->assertBanExists($params->ban_id);

		if (!$this->canManage()) {
			return $this->noPermission();
		}

		$ban->ban_status = 1;
		$ban->save();
		$note = $ban->newNote();
		$note->data = [
			'Ban Status' => 'Lifted',
		];
		$note->save();

		return $this->redirect($this->buildLink('bans', $ban));
	}

	public function actionReinstate(ParameterBag $params) {

		$ban = $this->assertBanExists($params->ban_id);

		if (!$this->canManage() || $ban->remaining_time == -2) {
			return $this->noPermission();
		}

		$ban->ban_review = 1;
		$ban->ban_status = 0;
		$ban->save();
		$note = $ban->newNote();
		$note->data = [
			'Review Status' => 'Approved',
			'Ban Status' => 'Reinstated',
		];
		$note->save();

		return $this->redirect($this->buildLink('bans', $ban));
	}

	public function canCreate() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id || !count($this->getSteamIDs()) || !$this->getPrimarySteamID())
		{
			return false;
		}
	
		return $visitor->hasPermission('bans', 'bans_create');
	}

	public function canView() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id || !count($this->getSteamIDs()) || !$this->getPrimarySteamID())
		{
			return false;
		}
	
		return $visitor->hasPermission('bans', 'bans_view');
	}

	public function canManage() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id || !count($this->getSteamIDs()) || !$this->getPrimarySteamID())
		{
			return false;
		}
	
		return $visitor->hasPermission('bans', 'bans_manage');
	}

	protected function getReplyAttachmentData($ban)
	{
		$attachmentHash = $ban->draft_reply->attachment_hash;

		/** @var \XF\Repository\Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		return $attachmentRepo->getEditorData('ban', $ban, $attachmentHash);
	}

	protected function getPrimarySteamID() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return 0;
		}

		$type = $this->getIdentityTypeRepo()->findIdentityType('steam');
		$identities = $type->getIdentitiesForUser($visitor->user_id);
		
		foreach ($identities as $key => $value) {
			if ($value->identity_type_id == $type->identity_type_id && $value->status == 1) {
				return $value->identity_value;
			}
		}

		return 0;
	}

	protected function getSteamIDs() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return [];
		}

		$type = $this->getIdentityTypeRepo()->findIdentityType('steam');
		$identities = $type->getIdentitiesForUser($visitor->user_id);
		
		$ids = [];
		foreach ($identities as $key => $value) {
			if ($value->identity_type_id == $type->identity_type_id) {
				$ids[] = $value->identity_value;
			}
		}

		return $ids;
	}

	protected function toSteam64($id) {
		if (preg_match('/^STEAM_/', $id)) {
			$parts = explode(':', $id);
			return bcadd(bcadd(bcmul($parts[2], '2'), '76561197960265728'), $parts[1]);
		} elseif (is_numeric($id) && strlen($id) < 16) {
			return bcadd($id, '76561197960265728');
		} else {
			return $id;
		}
	}

	protected function getBanRepo() {
		return $this->repository('Kieran\Bans:Ban');
	}

	protected function getIdentityTypeRepo() {
		return $this->repository('Kieran\Identity:IdentityType');
    }
    
    protected function getBanTypeRepo() {
		return $this->repository('Kieran\Bans:BanType');
	}

	protected function assertBanExists($id, $with = null, $phraseKey = null) {
		return $this->assertRecordExists('Kieran\Bans:Ban', $id, $with, $phraseKey);
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('kieran_bans_viewing');
	}
}