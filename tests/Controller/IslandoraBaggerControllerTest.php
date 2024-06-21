<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests for the IslandoraBaggerController
 */
class IslandoraBaggerControllerTest extends WebTestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser the client.
     */
    private $client;

    /**
     * @var string path to the queue for the test.
     */
    private $queue_path;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->client = WebTestCase::createClient();
        // Make a temp file for the queue so we can clean it up after.
        $this->queue_path = tempnam("", "islandora_bagger_queue_");
        $_ENV["ISLANDORA_BAGGER_QUEUE_PATH"] = $this->queue_path;
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->queue_path)) {
            unlink($this->queue_path);
        }
    }

    /**
     * Test a POST to create bag with no arguments.
     * @covers \App\Controller\IslandoraBaggerController::create
     */
    public function testCreateNoArgs(): void
    {
        $app_dir = dirname(__DIR__, 2) . '/var/islandora_bagger..yml';
        $this->client->request('POST', '/api/createbag');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        $response = $this->client->getResponse();
        $this->assertEquals(
          '["Entry for node  using configuration at ' . addcslashes($app_dir, '/') . ' added to queue."]',
          $response->getContent()
        );
    }

    /**
     * Test add to queue to create bag.
     * @covers \App\Controller\IslandoraBaggerController::create
     */
    public function testCreateNormal(): void
    {
        $app_dir = dirname(__DIR__, 2) . '/var/islandora_bagger.7.yml';
        $this->client->request('POST', '/api/createbag', [], [], [
          'HTTP_ISLANDORA_NODE_ID' => '7'
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        $response = $this->client->getResponse();
        $this->assertEquals(
          '["Entry for node 7 using configuration at ' . addcslashes($app_dir, '/') . ' added to queue."]',
          $response->getContent()
        );
    }

    /**
     * When the location log path doesn't exist you get an empty response.
     * @covers \App\Controller\IslandoraBaggerController::getLocation
     */
    public function testGetLocationNoLogFile(): void
    {
        $_ENV["ISLANDORA_BAGGER_LOCATION_LOG_PATH"] = '/var/doesnt_exist';
        $this->client->request('GET', '/api/createbag');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        $response = $this->client->getResponse();
        $this->assertEquals("[]", $response->getContent());
    }

    /**
     * When no Islandora-Node-ID header is passed and variables can't be found you get a 500 Server Error
     * @covers \App\Controller\IslandoraBaggerController::getLocation
     */
    public function testGetLocationNoNid(): void
    {
        $this->client->request('HEAD', '/api/createbag');
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * When the Nid is not found in the location log file.
     * @covers \App\Controller\IslandoraBaggerController::getLocation
     */
    public function testGetLocationNidNotFound(): void
    {
        $this->client->request('GET', '/api/createbag', [], [],
          [
            'HTTP_Islandora-Node-ID' => 5,
          ]
        );
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Normal request that matches a line in the location log file.
     * @covers \App\Controller\IslandoraBaggerController::getLocation
     */
    public function testGetLocation(): void
    {
        // Create a temp file to be our location file.
        $location_file = tempnam("", "islandora_bagger_log_");
        // Assign the path to the environment variable so it is picked up by the container.
        $_ENV["ISLANDORA_BAGGER_LOCATION_LOG_PATH"] = $location_file;
        $date = date(\DateTime::ISO8601);
        // Populate our location log file.
        file_put_contents($location_file, "5\t/some/location\t$date\n");
        $this->client->request('GET', '/api/createbag', [], [],
          [
            'HTTP_Islandora-Node-ID' => 5,
          ]
        );
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        $response = $this->client->getResponse();
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("nid", $json);
        $this->assertEquals("5", $json["nid"]);
        $this->assertArrayHasKey("location", $json);
        $this->assertEquals("/some/location", $json["location"]);
        $this->assertArrayHasKey("created", $json);
        $this->assertEquals($date, $json["created"]);
        unlink($location_file);
    }

    /**
     * Test getting an empty queue.
     * @covers \App\Controller\IslandoraBaggerController::getQueue
     */
    public function testGetQueueEmpty(): void
    {
        $this->client->request('GET', '/api/queue');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        $this->assertSame(
          '{"message":"Queue file not found at ' . addcslashes($this->queue_path, '/') . '"}',
          $this->client->getResponse()->getContent()
        );
    }

    /**
     * Test getting a queue with one record.
     * @covers \App\Controller\IslandoraBaggerController::getQueue
     */
    public function testGetQueueWithRecord(): void
    {
        $yaml_path = dirname(__DIR__, 2) . '/var/islandora_bagger.99.yml';
        // Populate the queue.
        $this->client->request('POST', '/api/createbag', [], [], [
          'HTTP_ISLANDORA_NODE_ID' => '99'
        ]);
        $this->assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/queue');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        // Use startsWith to avoid the hassle of matching the datetime exactly.
        $this->assertStringStartsWith(
          '["99\t' . addcslashes($yaml_path, '/') . '\t',
          $this->client->getResponse()->getContent()
        );
    }

    /**
     * Test getting a queue with multiple records.
     * @covers \App\Controller\IslandoraBaggerController::getQueue
     */
    public function testGetQueueWithMultipleRecords(): void
    {
        $yaml_path_prefix = dirname(__DIR__, 2) . '/var/islandora_bagger.';
        $ids = [];
        // Generate some random numbers
        for ($x=0; $x<3; $x+=1) {
            do {
                // nothing
            } while (in_array($id = rand(1, 100), $ids));
            $ids[] = $id;
        }

        $expected_lines = [];
        // Populate the queue.
        foreach ($ids as $id) {
            $this->client->request('POST', '/api/createbag', [], [], [
              'HTTP_ISLANDORA_NODE_ID' => $id,
            ]);
            $this->assertResponseStatusCodeSame(200);
            $expected_lines[] = $id . "\t" . $yaml_path_prefix . $id . ".yml\t";
        }
        $this->client->request('GET', '/api/queue');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame("Content-type", "application/json");
        // Use startsWith to avoid the hassle of matching the datetime exactly.
        $json = json_decode($this->client->getResponse()->getContent());
        $this->assertIsArray($json);
        $this->assertCount(3, $json);
        for ($x=0; $x<3; $x+=1) {
            $this->assertStringStartsWith(
              $expected_lines[$x],
              $json[$x]
            );
        }
    }
}