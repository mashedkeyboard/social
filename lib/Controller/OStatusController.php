<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Social\Controller;


use daita\MySmallPhpTools\Exceptions\ArrayNotFoundException;
use daita\MySmallPhpTools\Traits\Nextcloud\TNCDataResponse;
use daita\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OCA\Social\AppInfo\Application;
use OCA\Social\Exceptions\InvalidResourceException;
use OCA\Social\Exceptions\RetrieveAccountFormatException;
use OCA\Social\Service\AccountService;
use OCA\Social\Service\CacheActorService;
use OCA\Social\Service\CurlService;
use OCA\Social\Service\MiscService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;


class OStatusController extends Controller {


	use TNCDataResponse;
	use TArrayTools;


	/** @var CacheActorService */
	private $cacheActorService;

	/** @var AccountService */
	private $accountService;

	/** @var CurlService */
	private $curlService;

	/** @var MiscService */
	private $miscService;

	/** @var IUserManager */
	private $userSession;


	/**
	 * OStatusController constructor.
	 *
	 * @param IRequest $request
	 * @param CacheActorService $cacheActorService
	 * @param AccountService $accountService
	 * @param CurlService $curlService
	 * @param MiscService $miscService
	 * @param IUserSession $userSession
	 */
	public function __construct(
		IRequest $request, CacheActorService $cacheActorService, AccountService $accountService,
		CurlService $curlService, MiscService $miscService, IUserSession $userSession
	) {
		parent::__construct(Application::APP_NAME, $request);

		$this->cacheActorService = $cacheActorService;
		$this->accountService = $accountService;
		$this->curlService = $curlService;
		$this->miscService = $miscService;
		$this->userSession = $userSession;
	}


	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string $uri
	 *
	 * @return Response
	 */
	public function subscribe(string $uri): Response {
		try {

			try {
				$actor = $this->cacheActorService->getFromAccount($uri);
			} catch (InvalidResourceException $e) {
				$actor = $this->cacheActorService->getFromId($uri);
			}

			$user = $this->userSession->getUser();
			if ($user === null) {
				throw new Exception('Failed to retrieve current user');
			}

			return new TemplateResponse(
				'social', 'ostatus', [
				'serverData' => [
					'account'     => $actor->getAccount(),
					'currentUser' => [
						'uid'         => $user->getUID(),
						'displayName' => $user->getDisplayName(),
					]
				]
			], 'guest'
			);
		} catch (Exception $e) {
			return $this->fail($e);
		}
	}


	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @param string $local
	 *
	 * @return Response
	 */
	public function followRemote(string $local): Response {
		try {
			$following = $this->accountService->getActor($local);

			return new TemplateResponse(
				'social', 'ostatus', [
				'serverData' => [
					'local'   => $local,
					'account' => $following->getAccount()
				]
			], 'guest'
			);
		} catch (Exception $e) {
			return $this->fail($e);
		}
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * @param string $local
	 * @param string $account
	 *
	 * @return Response
	 */
	public function getLink(string $local, string $account): Response {

		try {
			$following = $this->accountService->getActor($local);
			$result = $this->curlService->webfingerAccount($account);

			try {
				$link = $this->extractArray(
					'rel', 'http://ostatus.org/schema/1.0/subscribe',
					$this->getArray('links', $result)
				);
			} catch (ArrayNotFoundException $e) {
				throw new RetrieveAccountFormatException();
			}

			$template = $this->get('template', $link, '');
			$url = str_replace('{uri}', $following->getAccount(), $template);

			return $this->success(['url' => $url]);
		} catch (Exception $e) {
			return $this->fail($e);
		}
	}

}

