<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

function findMatches($item_id, $type) {
    $database = new Database();
    $conn = $database->getConnection();
    
    $opposite_type = ($type === 'lost') ? 'found' : 'lost';
    
    // Get the item details
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) return [];
    
    // Build matching query based on multiple criteria
    $query = "SELECT i.*, 
              c.name as category_name,
              -- Calculate similarity score
              (
                  -- Category match: +30 points
                  IF(i.category_id = :category_id, 30, 0) +
                  -- Location match: +25 points
                  (IF(LOWER(i.location) LIKE CONCAT('%', LOWER(:location), '%'), 25, 0)) +
                  -- Date proximity: +20 points (less points for further dates)
                  (20 - LEAST(ABS(DATEDIFF(i.date_occurred, :date_occurred)), 20)) +
                  -- Keyword matching: +25 points
                  (CASE 
                      WHEN LOWER(i.title) LIKE CONCAT('%', LOWER(:title_keyword), '%') THEN 15
                      WHEN LOWER(i.description) LIKE CONCAT('%', LOWER(:title_keyword), '%') THEN 10
                      ELSE 0
                  END +
                  CASE
                      WHEN LOWER(i.description) LIKE CONCAT('%', LOWER(:desc_keyword), '%') THEN 10
                      ELSE 0
                  END)
              ) as similarity_score
              FROM items i
              LEFT JOIN categories c ON i.category_id = c.id
              WHERE i.type = :opposite_type
              AND i.status = 'open'
              AND i.id != :item_id
              HAVING similarity_score > 30
              ORDER BY similarity_score DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':category_id' => $item['category_id'],
        ':location' => $item['location'],
        ':date_occurred' => $item['date_occurred'],
        ':title_keyword' => $item['title'],
        ':desc_keyword' => $item['title'],
        ':opposite_type' => $opposite_type,
        ':item_id' => $item_id
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function checkAndNotifyMatches($new_item_id, $type) {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Find matches
    $matches = findMatches($new_item_id, $type);
    
    if (empty($matches)) return;
    
    // Get current item details
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$new_item_id]);
    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    foreach ($matches as $match) {
        // Check if match already exists
        $check = $conn->prepare("SELECT id FROM matches WHERE (lost_item_id = ? AND found_item_id = ?) OR (lost_item_id = ? AND found_item_id = ?)");
        if ($type === 'lost') {
            $check->execute([$new_item_id, $match['id'], $match['id'], $new_item_id]);
        } else {
            $check->execute([$match['id'], $new_item_id, $new_item_id, $match['id']]);
        }
        if ($check->fetch()) continue;
        
        // Save match to database
        $stmt = $conn->prepare("INSERT INTO matches (lost_item_id, found_item_id, similarity_score) VALUES (?, ?, ?)");
        
        if ($type === 'lost') {
            $stmt->execute([$new_item_id, $match['id'], $match['similarity_score']]);
            $lost_item = $current_item;
            $found_item = $match;
        } else {
            $stmt->execute([$match['id'], $new_item_id, $match['similarity_score']]);
            $lost_item = $match;
            $found_item = $current_item;
        }
        
        // Get the owner of the opposite item
        $owner_id = $match['user_id'];
        $stmt = $conn->prepare("SELECT email, full_name, email_notifications FROM users WHERE id = ?");
        $stmt->execute([$owner_id]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create in-app notification
        createNotification(
            $owner_id,
            'Possible Match Found!',
            "We found a {$match['type']} item that might match your report: {$match['title']}",
            'match',
            SITE_URL . "/item.php?id={$match['id']}"
        );
        
        // Send email notification if enabled
        if ($owner['email_notifications']) {
            try {
                sendMatchNotificationEmail(
                    $owner['email'],
                    $owner['full_name'],
                    $lost_item,
                    $found_item
                );
            } catch (Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
        }
    }
}
?>
