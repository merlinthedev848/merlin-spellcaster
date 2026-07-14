<?php
declare(strict_types=1);

/**
 * Diagnostic test page for upgraded Inbox Monitor (Bounces & Replies parsing & tagging)
 */

header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo "MERLIN INBOX MONITOR DIAGNOSTIC SUITE\n";
echo "==========================================\n\n";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/InboxMonitor.php';

$db = Database::getConnection();

// Helper to log test status
function testLog(string $name, bool $passed, string $details = ''): void {
    $badge = $passed ? "[ PASS ]" : "[ FAIL ]";
    echo "{$badge} {$name}\n";
    if ($details) {
        echo "         Detail: {$details}\n";
    }
    echo "------------------------------------------\n";
}

// Ensure mock contacts exist for testing
$testBounceEmail = 'inbox-bounce-test@example.com';
$testReplyEmail = 'inbox-reply-test@example.com';

// Clean old tests
$db->prepare("DELETE FROM subscribers WHERE email IN (?, ?)")->execute([$testBounceEmail, $testReplyEmail]);

// Insert fresh mock subscribers
$db->prepare("INSERT INTO subscribers (email, first_name, last_name, status, created_at) VALUES (?, 'Bounce', 'Tester', 'active', NOW())")->execute([$testBounceEmail]);
$db->prepare("INSERT INTO subscribers (email, first_name, last_name, status, created_at) VALUES (?, 'Reply', 'Tester', 'active', NOW())")->execute([$testReplyEmail]);

$stGetBounce = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
$stGetBounce->execute([$testBounceEmail]);
$bounceSubId = (int)$stGetBounce->fetchColumn();

$stGetReply = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
$stGetReply->execute([$testReplyEmail]);
$replySubId = (int)$stGetReply->fetchColumn();

// Add some initial tags to verify tag clearing works on bounce
$stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
$stTag->execute(['newsletter']);
$newsletterTagId = $stTag->fetchColumn();
if (!$newsletterTagId) {
    $db->prepare("INSERT INTO tags (name, color) VALUES ('newsletter', '#319795')")->execute();
    $newsletterTagId = (int)$db->lastInsertId();
} else {
    $newsletterTagId = (int)$newsletterTagId;
}

$db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$bounceSubId, $newsletterTagId]);
$db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$replySubId, $newsletterTagId]);


// ---------------------------------------------------------------------
// TEST 1: Parse Bounce Emails (Regex Validation)
// ---------------------------------------------------------------------
$mockBounceHeader = (object)[
    'subject' => 'Delivery Status Notification (Failure)',
    'from' => [
        (object)['mailbox' => 'mailer-daemon', 'host' => 'googlemail.com']
    ]
];
$mockBounceBody = "Subject: Undelivered Mail Returned to Sender\n\nFinal-Recipient: rfc822; inbox-bounce-test@example.com\nAction: failed\nStatus: 5.1.1";

$reflectedMethod = new ReflectionMethod('InboxMonitor', 'parseBounceEmail');
$reflectedMethod->setAccessible(true);
$parsedEmail = $reflectedMethod->invoke(null, $mockBounceHeader, $mockBounceBody);

testLog(
    "Bounce Detection Regex Parsing",
    $parsedEmail === $testBounceEmail,
    "Expected target '{$testBounceEmail}', parsed '{$parsedEmail}'"
);


// ---------------------------------------------------------------------
// TEST 2: Parse Normal Replies (Ignore Bounces)
// ---------------------------------------------------------------------
$mockReplyHeader = (object)[
    'subject' => 'Re: Awesome newsletter!',
    'from' => [
        (object)['mailbox' => 'reply-user', 'host' => 'example.com']
    ]
];
$mockReplyBody = "Hey Merlin Team,\n\nI love this product, thanks!";
$parsedReplyAsBounce = $reflectedMethod->invoke(null, $mockReplyHeader, $mockReplyBody);

testLog(
    "Filter Normal Replies (Not Classified as Bounces)",
    $parsedReplyAsBounce === null,
    "Expected null, parsed '" . json_encode($parsedReplyAsBounce) . "'"
);


// ---------------------------------------------------------------------
// TEST 3: DB Execution of Bounced Contacts (Clear Tags, Set 'bounced' Status/Tag)
// ---------------------------------------------------------------------
$reflectedBounceMethod = new ReflectionMethod('InboxMonitor', 'markAsBounced');
$reflectedBounceMethod->setAccessible(true);
$reflectedBounceMethod->invoke(null, $db, $bounceSubId, 'Mock Hard Bounce Event');

// Check subscriber record status
$stCheckSub = $db->prepare("SELECT status FROM subscribers WHERE id = ?");
$stCheckSub->execute([$bounceSubId]);
$bounceStatus = $stCheckSub->fetchColumn();

// Check tags mapped to the subscriber
$stCheckTags = $db->prepare("
    SELECT t.name FROM subscriber_tags st
    JOIN tags t ON st.tag_id = t.id
    WHERE st.subscriber_id = ?
");
$stCheckTags->execute([$bounceSubId]);
$bounceTags = $stCheckTags->fetchAll(PDO::FETCH_COLUMN);

$bounceSubPassed = ($bounceStatus === 'bounced') && (count($bounceTags) === 1) && ($bounceTags[0] === 'bounced');
testLog(
    "Database Updates for Bounced Subscribers",
    $bounceSubPassed,
    "Status: '{$bounceStatus}' (Expected: 'bounced'). Assigned tags: " . implode(', ', $bounceTags) . " (Expected only 'bounced')"
);


// ---------------------------------------------------------------------
// TEST 4: DB Execution of Replied Contacts (Retain tags, Append 'replied' Tag, Log Activity)
// ---------------------------------------------------------------------
$reflectedReplyMethod = new ReflectionMethod('InboxMonitor', 'markAsReplied');
$reflectedReplyMethod->setAccessible(true);
$reflectedReplyMethod->invoke(null, $db, $replySubId, 'Re: Awesome newsletter!');

// Check status remains active
$stCheckSub->execute([$replySubId]);
$replyStatus = $stCheckSub->fetchColumn();

// Check tags mapped to the subscriber
$stCheckTags->execute([$replySubId]);
$replyTags = $stCheckTags->fetchAll(PDO::FETCH_COLUMN);

// Check activity log contains the reply interaction
$stCheckActivity = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE subscriber_id = ? AND action = 'reply'");
$stCheckActivity->execute([$replySubId]);
$activityCount = (int)$stCheckActivity->fetchColumn();

$replySubPassed = ($replyStatus === 'active') && in_array('newsletter', $replyTags) && in_array('replied', $replyTags) && ($activityCount > 0);
testLog(
    "Database Updates for Replied Contacts",
    $replySubPassed,
    "Status: '{$replyStatus}' (Expected: 'active'). Tags: " . implode(', ', $replyTags) . " (Expected newsletter & replied). Activity Logs: {$activityCount}."
);

// Clean up test data
$db->prepare("DELETE FROM subscribers WHERE email IN (?, ?)")->execute([$testBounceEmail, $testReplyEmail]);

echo "All tests executed.\n";
