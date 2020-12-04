<?php

namespace Drupal\newsletter_mailjet;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Mailjet\Client;
use Mailjet\Resources;

/**
 * Interacts with the Mailjet API.
 */
class Mailjet {

  /**
   * @var \Mailjet\Client
   */
  protected $client;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Mailjet constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *
   * @throws \Exception
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    if (!Settings::get('mailjet')) {
      throw new \Exception('Mailjet is not configured.');
    }

    $credentials = Settings::get('mailjet');
    $this->client = new Client($credentials['key'], $credentials['secret'], ['version' => 'v3']);
    $this->logger = $loggerChannelFactory->get('mailjet');
  }

  /**
   * Subscribes a contact by email to a given list.
   *
   * @param $email
   *   The email address.
   * @param $list_id
   *   The list ID.
   * @param bool $force
   *   Whether to force the creation if the subscription is already there or
   *   the contact was unsubscribed.
   * @param array $values
   *   Contact properties to set.
   *
   * @return array
   *   The response information.
   */
  public function subscribeContact($email, $list_id, $force = FALSE, array $values = []) {
    $action = $force ? 'addforce' : 'addnoforce';
    $body = [
      'Action' => $action,
      'Email' => $email,
      'Properties' => (object) $values
    ];

    $response = $this->client->post(Resources::$ContactslistManagecontact, ['id' => $list_id, 'body' => $body]);
    if ($response->success()) {
      $this->logger->info('Added contact with email @email to list @list.', ['@email' => $email, '@list' => $list_id]);
      return $response->getBody()['Data'][0];
    }

    $this->logger->error('Failed to add contact with ID @email to list @list. Error: @error', ['@email' => $email, '@list' => $list_id, '@error' => $response->getBody()['ErrorMessage']]);
    return [];
  }

  /**
   * Finds a contact by email and returns its data.
   *
   * @param $email
   *   The email address.
   *
   * @return array
   *   The contact information.
   */
  public function getContact($email) {
    $response = $this->client->get(Resources::$Contact, ['id' => $email]);

    if ($response->success() && $response->getData()) {
      return $response->getData()[0];
    }

    return [];
  }

  /**
   * Creates a contact and returns its details.
   *
   * @param $email
   *   The email address.
   * @param array $values
   *   Contact values.
   *
   * @return array
   *   The contact information.
   */
  public function createContact($email, array $values = []) {
    $values['Email'] = $email;
    $response = $this->client->post(Resources::$Contact, ['body' => $values]);
    if ($response->success()) {
      $contact = $response->getData()[0];
      $this->logger->info('Created contact with email @email and ID @id.', ['@email' => $email, '@id' => $contact['ID']]);
      return $contact;
    }

    if ($response->getStatus() === 401) {
      $this->logger->error('Failed to create contact with email @email. Error: @error', ['@email' => $email, '@error' => 'Unauthorised']);
      return [];
    }

    $this->logger->error('Failed to create contact with email @email. Error: @error', ['@email' => $email, '@error' => $response->getBody()['ErrorMessage']]);
    return [];
  }

  /**
   * Adds a contact to a contact list.
   *
   * @param string $id
   *   The contact ID.
   * @param string $list
   *   The list ID.
   */
  public function addContactToList($id, $list) {
    $body = [
      'IsUnsubscribed' => "false",
      'ContactID' => $id,
      'ListID' => $list,
    ];
    $response = $this->client->post(Resources::$Listrecipient, ['body' => $body]);
    if ($response->success()) {
      $this->logger->info('Added contact with ID @id to list @list.', ['@id' => $id, '@list' => $list]);
      return;
    }

    $this->logger->error('Failed to add contact with ID @id to list @list. Error: @error', ['@id' => $id, '@list' => $list, '@error' => $response->getBody()['ErrorMessage']]);
  }

}
