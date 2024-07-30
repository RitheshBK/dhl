<?php

namespace Drupal\Tests\dhl_location_finder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the DHL Location Finder module.
 *
 * @group dhl_location_finder
 */
class DhlLocationFinderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dhl_location_finder'];

  /**
   * Tests the form submission.
   */
  public function testFormSubmission() {
    $this->drupalGet('dhl-location-finder');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'country' => 'Czechia',
      'city' => 'Prague',
      'postal_code' => '11000',
    ];
    $this->drupalPostForm(NULL, $edit, 'Find Locations');
    $this->assertSession()->pageTextContains('Packstation 103');
  }
}
