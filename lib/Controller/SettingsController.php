<?php

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2015 Johannes Starosta <j.starosta@tu-braunschweig.de>
 * SPDX-FileCopyrightText: 2015 Pellaeon Lin <pellaeon@hs.ntnu.edu.tw>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Registration\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {

	private IL10N $l10n;
	private IConfig $config;
	private IGroupManager $groupmanager;
	/** @var string */
	protected $appName;

	public function __construct($appName, IRequest $request, IL10N $l10n, IConfig $config, IGroupManager $groupmanager) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
		$this->groupmanager = $groupmanager;
		$this->appName = $appName;
	}

	/**
	 * @AdminRequired
	 *
	 * @param string|null $registered_user_group all newly registered user will be put in this group
	 * @param string $allowed_domains Registrations are only allowed for E-Mailadresses with these domains
	 * @param string|null $domain_groups Groups linked to configured E-Mail domains
	 * @param string $additional_hint show Text at user-creation form
	 * @param string $email_verification_hint if filled embed Text in Verification mail send to user
	 * @param string $username_policy_regex optional regex to check usernames against a pattern
	 * @param bool|null $admin_approval_required newly registered users have to be validated by an admin
	 * @param bool|null $email_is_optional email address is not required
	 * @param bool|null $email_is_login email address is forced as user id
	 * @param bool|null $domains_is_blocklist is the domain list an allow or block list
	 * @param bool|null $show_domains should the email list be shown to the user or not
	 * @return DataResponse
	 */
	public function admin(?string $registered_user_group,
		string $allowed_domains,
		string $additional_hint,
		string $email_verification_hint,
		string $username_policy_regex,
		?bool $admin_approval_required,
		?bool $email_is_optional,
		?bool $email_is_login,
		?bool $show_fullname,
		?bool $enforce_fullname,
		?bool $show_phone,
		?bool $enforce_phone,
		?bool $domains_is_blocklist,
		?bool $show_domains,
		?bool $disable_email_verification,
		?string $domain_groups = null): DataResponse {
		// handle domains
		$allowedDomains = $this->normalizeDomains($allowed_domains);
		$this->saveAllowedDomains($allowedDomains);

		if ($domain_groups !== null) {
			$response = $this->saveDomainGroups($domain_groups, $allowedDomains);
			if ($response instanceof DataResponse) {
				return $response;
			}
		}

		// handle hints
		if (($additional_hint === '') || ($additional_hint === null)) {
			$this->config->deleteAppValue($this->appName, 'additional_hint');
		} else {
			$this->config->setAppValue($this->appName, 'additional_hint', $additional_hint);
		}

		if (($email_verification_hint === '') || ($email_verification_hint === null)) {
			$this->config->deleteAppValue($this->appName, 'email_verification_hint');
		} else {
			$this->config->setAppValue($this->appName, 'email_verification_hint', $email_verification_hint);
		}

		//handle regex
		if (($username_policy_regex === '') || ($username_policy_regex === null)) {
			$this->config->deleteAppValue($this->appName, 'username_policy_regex');
		} elseif ((@preg_match($username_policy_regex, null) === false)) {
			// validate regex
			return new DataResponse([
				'data' => [
					'message' => $this->l10n->t('Invalid username policy regex'),
				],
				'status' => 'error',
			], Http::STATUS_BAD_REQUEST);
		} else {
			$this->config->setAppValue($this->appName, 'username_policy_regex', $username_policy_regex);
		}

		$this->config->setAppValue($this->appName, 'admin_approval_required', $admin_approval_required ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'email_is_optional', $email_is_optional ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'email_is_login', !$email_is_optional && $email_is_login ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'show_fullname', $show_fullname ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'enforce_fullname', $enforce_fullname ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'show_phone', $show_phone ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'enforce_phone', $enforce_phone ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'domains_is_blocklist', $domains_is_blocklist ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'show_domains', $show_domains ? 'yes' : 'no');
		$this->config->setAppValue($this->appName, 'disable_email_verification', $disable_email_verification ? 'yes' : 'no');

		if ($registered_user_group === null) {
			$this->config->deleteAppValue($this->appName, 'registered_user_group');
		} elseif (($group = $this->groupmanager->get($registered_user_group)) instanceof IGroup) {
			$this->config->setAppValue($this->appName, 'registered_user_group', $registered_user_group);
		} else {
			return new DataResponse([
				'data' => [
					'message' => $this->l10n->t('No such group'),
				],
				'status' => 'error',
			], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([
			'data' => [
				'message' => $this->l10n->t('Saved'),
			],
			'status' => 'success',
		]);
	}

	/**
	 * @AdminRequired
	 */
	public function domainGroups(string $allowed_domains, string $domain_groups): DataResponse {
		$allowedDomains = $this->normalizeDomains($allowed_domains);
		$this->saveAllowedDomains($allowedDomains);

		$response = $this->saveDomainGroups($domain_groups, $allowedDomains);
		if ($response instanceof DataResponse) {
			return $response;
		}

		return new DataResponse([
			'data' => [
				'message' => $this->l10n->t('Saved'),
			],
			'status' => 'success',
		]);
	}

	/**
	 * @param string[] $allowedDomains
	 */
	private function saveAllowedDomains(array $allowedDomains): void {
		if ($allowedDomains === []) {
			$this->config->deleteAppValue($this->appName, 'allowed_domains');
		} else {
			$this->config->setAppValue($this->appName, 'allowed_domains', implode(';', $allowedDomains));
		}
	}

	/**
	 * @param string[] $allowedDomains
	 */
	private function saveDomainGroups(string $domainGroups, array $allowedDomains): ?DataResponse {
		$domainGroups = $this->decodeDomainGroups($domainGroups);
		if ($domainGroups === null) {
			return new DataResponse([
				'data' => [
					'message' => $this->l10n->t('Invalid domain group configuration'),
				],
				'status' => 'error',
			], Http::STATUS_BAD_REQUEST);
		}

		$domainGroups = $this->filterDomainGroups($domainGroups, $allowedDomains);
		if ($domainGroups === null) {
			return new DataResponse([
				'data' => [
					'message' => $this->l10n->t('No such group'),
				],
				'status' => 'error',
			], Http::STATUS_NOT_FOUND);
		}

		if ($domainGroups === []) {
			$this->config->deleteAppValue($this->appName, 'domain_groups');
		} else {
			$encodedDomainGroups = json_encode($domainGroups);
			if ($encodedDomainGroups === false) {
				return new DataResponse([
					'data' => [
						'message' => $this->l10n->t('Invalid domain group configuration'),
					],
					'status' => 'error',
				], Http::STATUS_BAD_REQUEST);
			}
			$this->config->setAppValue($this->appName, 'domain_groups', $encodedDomainGroups);
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	private function normalizeDomains(?string $allowedDomains): array {
		$domains = explode(';', $allowedDomains ?? '');
		$domains = array_map(static function (string $domain): string {
			return strtolower(trim($domain));
		}, $domains);
		$domains = array_filter($domains, static function (string $domain): bool {
			return $domain !== '';
		});

		return array_values(array_unique($domains));
	}

	/**
	 * @return array<string, string>|null
	 */
	private function decodeDomainGroups(?string $domainGroups): ?array {
		if ($domainGroups === null || $domainGroups === '') {
			return [];
		}

		$domainGroups = json_decode($domainGroups, true);
		if (!is_array($domainGroups)) {
			return null;
		}

		$groups = [];
		foreach ($domainGroups as $domain => $gid) {
			if (!is_string($domain) || !is_string($gid)) {
				continue;
			}

			$domain = strtolower(trim($domain));
			$gid = trim($gid);
			if ($domain === '' || $gid === '') {
				continue;
			}

			$groups[$domain] = $gid;
		}

		return $groups;
	}

	/**
	 * @param array<string, string> $domainGroups
	 * @param string[] $allowedDomains
	 * @return array<string, string>|null
	 */
	private function filterDomainGroups(array $domainGroups, array $allowedDomains): ?array {
		$allowedDomains = array_flip($allowedDomains);
		$groups = [];

		foreach ($domainGroups as $domain => $gid) {
			if (!isset($allowedDomains[$domain])) {
				continue;
			}

			if (!$this->groupmanager->get($gid) instanceof IGroup) {
				return null;
			}

			$groups[$domain] = $gid;
		}

		return $groups;
	}
}
