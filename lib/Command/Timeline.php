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


namespace OCA\Social\Command;


use daita\MySmallPhpTools\Exceptions\CacheItemNotFoundException;
use Exception;
use OC\Core\Command\Base;
use OCA\Social\AP;
use OCA\Social\Db\StreamRequest;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\RedundancyLimitException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Model\ActivityPub\Actor\Person;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Service\CacheActorService;
use OCA\Social\Service\ConfigService;
use OCA\Social\Service\MiscService;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Stream
 *
 * @package OCA\Social\Command
 */
class Timeline extends Base {

	/** @var IUserManager */
	private $userManager;

	/** @var StreamRequest */
	private $streamRequest;

	/** @var CacheActorService */
	private $cacheActorService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/** @var OutputInterface */
	private $output;

	/** @var bool */
	private $asJson;

	/** @var int */
	private $count;


	/**
	 * Stream constructor.
	 *
	 * @param IUserManager $userManager
	 * @param StreamRequest $streamRequest
	 * @param CacheActorService $cacheActorService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IUserManager $userManager, StreamRequest $streamRequest,
		CacheActorService $cacheActorService, ConfigService $configService,
		MiscService $miscService
	) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->streamRequest = $streamRequest;
		$this->cacheActorService = $cacheActorService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 *
	 */
	protected function configure() {
		parent::configure();
		$this->setName('social:stream')
			 ->addArgument('userId', InputArgument::REQUIRED, 'viewer')
			 ->addArgument('timeline', InputArgument::REQUIRED, 'timeline')
			 ->addOption('count', '', InputOption::VALUE_REQUIRED, 'number of elements', '5')
			 ->addOption('json', '', InputOption::VALUE_NONE, 'return JSON format')
			 ->setDescription('Get stream by timeline and viewer');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$output = new ConsoleOutput();
		$this->output = $output->section();

		$this->asJson = $input->getOption('json');
		$this->count = intval($input->getOption('count'));

		$timeline = $input->getArgument('timeline');

		$userId = $input->getArgument('userId');
		if ($this->userManager->get($userId) === null) {
			throw new Exception('Unknown user');
		}

		$actor = $this->cacheActorService->getFromLocalAccount($userId);

		$this->outputActor($actor);
		$this->displayStream($actor, $timeline);
	}


	/**
	 * @param Person $actor
	 */
	private function outputActor(Person $actor) {
		if ($this->asJson) {
			return;
		}

		$this->output->writeln('<info>Account</info>: ' . $actor->getAccount());
		$this->output->writeln('<info>Id</info>: ' . $actor->getId());
		$this->output->writeln('');

	}


	/**
	 * @param Person $actor
	 * @param string $timeline
	 *
	 * @throws Exception
	 */
	private function displayStream(Person $actor, string $timeline) {
		switch ($timeline) {
			case 'home':
				$stream = $this->streamRequest->getTimelineHome($actor, 0, $this->count);
				$this->outputStream($stream);
				break;

			case 'direct':
				$stream = $this->streamRequest->getTimelineDirect($actor, 0, $this->count);
				$this->outputStream($stream);
				break;

			case 'notifications':
				$stream = $this->streamRequest->getTimelineNotifications($actor, 0, $this->count);
				$this->outputStream($stream);
				break;

			case 'local':
				$stream = $this->streamRequest->getTimelineGlobal(0, $this->count, true);
				$this->outputStream($stream);
				break;

			case 'global':
				$stream = $this->streamRequest->getTimelineGlobal(0, $this->count, false);
				$this->outputStream($stream);
				break;

			default:
				throw new Exception(
					'Unknown timeline. Try home, direct, local, global, notification.'
				);
		}
	}


	/**
	 * @param Stream[] $stream
	 */
	private function outputStream(array $stream) {
		if ($this->asJson) {
			$this->output->writeln(json_encode($stream, JSON_PRETTY_PRINT));
		}

		$table = new Table($this->output);
		$table->setHeaders(['Id', 'Source', 'Type', 'Author', 'Content']);
		$table->render();
		$this->output->writeln('');

		foreach ($stream as $item) {
			$objectId = $item->getObjectId();
			$cache = $item->getCache();
			$content = '';
			$author = '';
			if ($objectId !== '' && $cache->hasItem($objectId)) {
				try {
					$cachedObject = $cache->getItem($objectId)
										  ->getObject();

					/** @var Stream $cachedItem */
					$cachedItem = AP::$activityPub->getItemFromData($cachedObject);
					$content = $cachedItem->getContent();
					$author = $cachedItem->getActor()
										 ->getAccount();
				} catch (CacheItemNotFoundException $e) {
				} catch (ItemUnknownException $e) {
				} catch (RedundancyLimitException $e) {
				} catch (SocialAppConfigException $e) {
				}
			} else {
				$content = $item->getContent();
				$author = $item->getActor()
							   ->getAccount();
			}

			$table->appendRow(
				[
					'<comment>' . $item->getId() . '</comment>',
					'<info>' . $item->getActor()
									->getAccount() . '</info>',
					$item->getType(),
					'<info>' . $author . '</info>',
					$content,
				]
			);
		}


	}


}

