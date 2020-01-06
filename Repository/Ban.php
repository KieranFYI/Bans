<?php

namespace Kieran\Bans\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Ban extends Repository
{

	public static function getAvailableSorts()
	{
		return [
			'timestamp' => 'timestamp',
			'ban_review' => 'ban_review',
			'ban_type' => 'type_id',
		];
	}

	public static function getBanTimes() {
		return [
			[
				'time' => 1440,
				'label' => '1 Day',
			],
			[
				'time' => 4320,
				'label' => '3 Day',
			],
			[
				'time' => 10080,
				'label' => '1 Week',
			],
			[
				'time' => 20160,
				'label' => '3 Weeks',
			],
			[
				'time' => 0,
				'label' => 'Permanent',
			],
		];
	}

	public static function getStatuses() {
		return [
			'0' => 'Unchecked',
			'1' => 'Approved',
			'2' => 'Awaiting Response',
			'3' => 'Disapproved',
		];
	}

	public function find($filters) {
		$finder = $this->buildQuery($filters);
		
		return $finder->fetch();
	}

	public function findPaged($filters, $page = 1, $perPage = 25) {
		$finder = $this->buildQuery($filters);
		$finder->limitByPage($page, $perPage);
		
		return $finder;
	}

	private function buildQuery($filters) {
		$finder = $this->finder('Kieran\Bans:Ban')->order('ban_id', 'desc');

		if (isset($filters['ply_user_id'])) {
			$finder->where('ply_user_id', $filters['ply_user_id']);
		}
		
		if (isset($filters['ply_id'])) {
			$finder->where('ply_id', $filters['ply_id']);
		}

		if (isset($filters['banned_uid'])) {
			$finder->where('target_id', $filters['banned_uid']);
		}
		
		if (isset($filters['status'])) {
			$finder->where('ban_status', $filters['status']);
		}
		
		if (isset($filters['type'])) {
			$finder->where('type_id', $filters['type']);
		}

		if (isset($filters['date'])) {
			$start = 0;
			$end = time();

			if (isset($filters['date']['start'])) {
				$start = strtotime($filters['date']['start']);
			}

			if (isset($filters['date']['end'])) {
				$end = strtotime($filters['date']['end']);
			}

			if ($end < $start) {
				$t = $start;
				$start = $end;
				$end = $t;
			}

			$finder->where(['timestamp', '>=', $start]);
			$finder->where(['timestamp', '<=', $end]);
		}

		if (isset($filters['order']) && isset($filters['direction']))
		{
			$finder->order(self::getAvailableSorts()[$filters['order']], $filters['direction']);
		}
		else
		{
			$finder->order('timestamp', 'desc');
		}

		return $finder;
	}

	public function setupBaseBan()
	{
		return $this->em->create('Kieran\Bans:Ban');
	}
}