<?php

namespace Drupal\newsletter_mailjet;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\newsletter_subscription\NewsletterSubscriberInterface;
use Drupal\newsletter_subscription\NewsletterSubscription;

/**
 * Subscribes to the Mailjet via the API.
 */
class MailjetSubscriber implements NewsletterSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The mailjet client.
   *
   * @var \Drupal\newsletter_mailjet\Mailjet
   */
  protected $mailjet;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * MailjetSubscriber constructor.
   *
   * @param \Drupal\newsletter_mailjet\Mailjet
   *   The mailjet client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(Mailjet $mailjet, LoggerChannelFactoryInterface $loggerChannelFactory, MessengerInterface $messenger) {
    $this->mailjet = $mailjet;
    $this->logger = $loggerChannelFactory->get('mailjet');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribeContact(NewsletterSubscription $subscription) {
    $data = $subscription->getData();
    $list_id = $data['list_id'] ?? NULL;
    if (!$list_id) {
      $this->logger->error('No list found in the subscription data for email @email.', ['@error' => $subscription->getEmail()]);
      $this->messenger->addError($this->t('There was a problem with your subscription. Please contact our team to remedy the problem.'));
      return;
    }

    unset($data['list_id']);
    $contact = $this->mailjet->subscribeContact($subscription->getEmail(), $list_id, TRUE);
    if (!$contact) {
      $this->messenger->addError($this->t('There was a problem with your subscription. Please contact our team to remedy the problem.'));
      return;
    }

    $this->messenger->addStatus('Thank you for subscribing to our newsletter.');
  }
}
