<?php
// Start session if not already started
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Fetch feedback from database
$feedback = [];
 
// Try to get feedback data with flexible column mapping
$sql = "SELECT * FROM feedback ORDER BY created_at DESC";
$res = $conn->query($sql);
 
// If that fails, try without ordering
if (!$res) {
    $sql = "SELECT * FROM feedback";
    $res = $conn->query($sql);
}
 
// Process results with flexible field mapping
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Map fields flexibly based on what's available in the table
        $mapped_row = [
            'id' => $row['feedback_id'] ?? $row['id'] ?? 0,
            'name' => $row['feedback_name'] ?? $row['name'] ?? $row['customer_name'] ?? 'Guest',
            'email' => $row['feedback_email'] ?? $row['email'] ?? $row['customer_email'] ?? 'N/A',
            'rating' => $row['feedback_rating'] ?? $row['rating'] ?? $row['stars'] ?? 0,
            'message' => $row['feedback_message'] ?? $row['message'] ?? $row['comment'] ?? $row['comments'] ?? '',
            'created_at' => $row['created_at'] ?? $row['date_created'] ?? $row['timestamp'] ?? 'N/A'
        ];
        $feedback[] = $mapped_row;
    }
}



// Calculate stats for the insight cards
$total_feedback = count($feedback);
$average_rating = 0;
$positive_feedback = 0;
$latest_feedback = array_slice($feedback, 0, 3); // For the sidebar activity

if ($total_feedback > 0) {
    $sum_rating = 0;
    foreach($feedback as $f) {
        $rating = isset($f['rating']) ? (int)$f['rating'] : 0;
        $sum_rating += $rating;
        if ($rating >= 4) $positive_feedback++;
    }
    $average_rating = round($sum_rating / $total_feedback, 1);
}

$positive_percent = $total_feedback > 0 ? round(($positive_feedback / $total_feedback) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Feedback - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <!-- Ensure this path is correct based on your folder structure -->
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  
  <style>
    /* Internal styles to complement adminstyle.css */
    .recent-orders {
        margin-top: 2rem;
        background: var(--clr-white);
        padding: var(--card-padding);
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        transition: all 300ms ease;
    }

    .recent-orders:hover {
        box-shadow: none;
    }

    .recent-orders table {
        width: 100%;
        border-collapse: collapse;
    }

    .recent-orders table th, .recent-orders table td {
        padding: 1.2rem 0.5rem;
        text-align: left;
        border-bottom: 1px solid var(--clr-light);
    }

    .rating-stars {
        color: #ffbb55;
        font-size: 1.1rem;
        letter-spacing: 2px;
    }

    .feedback-text-preview {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--clr-info-dark);
    }

    /* Modal Styling */
    #feedbackModal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        z-index: 3000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: var(--clr-white);
        padding: 2.5rem;
        border-radius: var(--card-border-radius);
        width: 90%;
        max-width: 550px;
        box-shadow: var(--box-shadow);
        position: relative;
        animation: modalFade 0.3s ease;
    }

    @keyframes modalFade {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    main .insights > div.positive-feedback span { background: var(--clr-success); }
    main .insights > div.avg-rating span { background: var(--clr-primary); }

    .view-btn {
        background: var(--clr-primary);
        color: var(--clr-white);
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius-1);
        cursor: pointer;
        font-weight: 500;
        transition: opacity 0.2s;
        border: none;
    }
    .view-btn:hover { opacity: 0.8; }
  </style>
</head>
<body class="admin-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>

   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>


      <main class="admin-page-main">

         <h1>Customer Feedback</h1>

         <div class="insights">
            <div class="total-feedback">
               <span class="material-symbols-sharp">forum</span>
               <div class="middle">
                  <div class="left">
                     <h3>Total Reviews</h3>
                     <h1><?php echo $total_feedback; ?></h1>
                  </div>
               </div>
               <small class="text-muted">Global customer count</small>
            </div>

            <div class="avg-rating">
               <span class="material-symbols-sharp">star</span>
               <div class="middle">
                  <div class="left">
                     <h3>Average Score</h3>
                     <h1><?php echo $average_rating; ?> / 5</h1>
                  </div>
               </div>
               <small class="text-muted">Satisfaction rating</small>
            </div>

            <div class="positive-feedback">
               <span class="material-symbols-sharp">thumb_up</span>
               <div class="middle">
                  <div class="left">
                     <h3>Positive Ratio</h3>
                     <h1><?php echo $positive_percent; ?>%</h1>
                  </div>
               </div>
               <small class="text-muted">High satisfaction (4+ stars)</small>
            </div>
         </div>

         <div class="recent-orders">
            <h2>Review History</h2>
            <table>
               <thead>
                  <tr>
                     <th>Customer</th>
                     <th>Email</th>
                     <th>Rating</th>
                     <th>Message Snippet</th>
                     <th>Date</th>
                     <th></th>
                  </tr>
               </thead>
               <tbody>
                  <?php if (empty($feedback)): ?>
                    <tr><td colspan="6" style="text-align:center;">No feedback yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($feedback as $item): ?>
                    <tr>
                       <td><b><?php echo htmlspecialchars($item['name'] ?? 'Guest'); ?></b></td>
                       <td><?php echo htmlspecialchars($item['email'] ?? 'N/A'); ?></td>
                       <td class="rating-stars">
                          <?php for($i=1; $i<=5; $i++) echo ($i <= ($item['rating'] ?? 0)) ? '★' : '☆'; ?>
                       </td>
                       <td class="feedback-text-preview"><?php echo htmlspecialchars(strlen($item['message'] ?? '') > 50 ? substr($item['message'] ?? '', 0, 50) . '...' : ($item['message'] ?? '')); ?></td>
                       <td class="text-muted"><?php echo isset($item['created_at']) ? date('M d', strtotime($item['created_at'])) : 'N/A'; ?></td>
                       <td>
                          <div class="d-flex gap-2">
                             <button class="view-btn" 
                                     onclick="openFeedbackModal('<?php echo addslashes($item['name'] ?? 'Guest'); ?>', '<?php echo addslashes($item['message'] ?? ''); ?>', <?php echo $item['rating'] ?? 0; ?>, '<?php echo isset($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : 'N/A'; ?>')">
                                View
                             </button>
                             <button class="btn-danger" 
                                     onclick="deleteFeedback(<?php echo $item['id'] ?? 0; ?>, '<?php echo addslashes(addslashes($item['name'] ?? 'Guest')); ?>', this)"
                                     style="padding: 0.5rem 1rem; border-radius: var(--border-radius-1);">
                                Delete
                             </button>
                          </div>
                       </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
               </tbody>
            </table>
         </div>
      </main>

      <div class="right">
         <div class="top">
            <button id="menu_btn"><span class="material-symbols-sharp">menu</span></button>
            <div class="theme-toggler">
               <span class="material-symbols-sharp active">light_mode</span>
               <span class="material-symbols-sharp">dark_mode</span>
            </div>
            <div class="profile">
               <div class="info">
                  <p>Hey, <b>Admin</b></p>
                  <small class="text-muted">Administrator</small>
               </div>
               <div class="profile-photo">
                  <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin" onerror="this.src='https://ui-avatars.com/api/?name=Admin&background=7380ec&color=fff'">
               </div>
            </div>
         </div>

         <div class="recent-updates">
            <h2>Review Activity</h2>
            <div class="updates">
               <?php if(empty($latest_feedback)): ?>
                  <p class="text-muted" style="padding: 1rem;">No recent activity</p>
               <?php else: ?>
                  <?php foreach($latest_feedback as $recent): ?>
                  <div class="update">
                     <div class="profile-photo">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($recent['name'] ?? 'User'); ?>&background=random" alt="User">
                     </div>
                     <div class="message">
                        <p><b><?php echo htmlspecialchars($recent['name'] ?? 'User'); ?></b> gave a <?php echo $recent['rating'] ?? 0; ?>-star rating.</p>
                        <small class="text-muted"><?php echo isset($recent['created_at']) ? date('H:i A', strtotime($recent['created_at'])) : ''; ?></small>
                     </div>
                  </div>
                  <?php endforeach; ?>
               <?php endif; ?>
            </div>
         </div>
      </div>
   </div>

   <div id="feedbackModal">
       <div class="modal-content">
           <span class="material-symbols-sharp" onclick="closeModal()" style="position: absolute; right: 1.5rem; top: 1.5rem; cursor: pointer; color: var(--clr-info-dark);">close</span>
           <h2 id="modalTitle" style="margin-bottom: 0.5rem; color: var(--clr-primary);">Review Details</h2>
           <p id="modalDate" class="text-muted" style="margin-bottom: 1.5rem;"></p>
           
           <div id="modalStars" class="rating-stars" style="margin-bottom: 1rem; font-size: 1.5rem;"></div>
           
           <div style="background: var(--clr-light); padding: 1.5rem; border-radius: var(--border-radius-2); margin-bottom: 1.5rem; max-height: 200px; overflow-y: auto;">
               <p id="modalMsgBody" style="line-height: 1.8; color: var(--clr-dark); white-space: pre-wrap;"></p>
           </div>
           
           <button onclick="closeModal()" class="view-btn" style="width: 100%; padding: 1rem;">Close Review</button>
       </div>
   </div>

      <script>
      function openFeedbackModal(name, message, rating, date) {
           document.getElementById('modalTitle').innerText = "Feedback from " + name;
           document.getElementById('modalDate').innerText = "Submitted on " + date;
           document.getElementById('modalMsgBody').innerText = message;
           
           let stars = "";
           for(let i=1; i<=5; i++) stars += (i <= rating) ? '★' : '☆';
           document.getElementById('modalStars').innerText = stars;
           
           document.getElementById('feedbackModal').style.display = 'flex';
       }

       function closeModal() {
           document.getElementById('feedbackModal').style.display = 'none';
       }

       // Create confirmation modal for delete operations
       function showDeleteConfirmation(itemName, onDeleteCallback) {
           // Remove any existing modal
           const existingModal = document.getElementById('deleteConfirmModal');
           if (existingModal) existingModal.remove();
           
           // Create modal HTML
           const modalHtml = `
               <div id="deleteConfirmModal" class="delete-confirm-overlay" style="
                   position: fixed;
                   top: 0;
                   left: 0;
                   width: 100%;
                   height: 100%;
                   background: rgba(0, 0, 0, 0.6);
                   backdrop-filter: blur(5px);
                   z-index: 5000;
                   display: flex;
                   justify-content: center;
                   align-items: center;
                   animation: fadeIn 0.3s ease;
               ">
                   <div class="delete-confirm-modal" style="
                       background: var(--clr-white);
                       padding: 2rem;
                       border-radius: var(--border-radius-2);
                       width: 90%;
                       max-width: 450px;
                       box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                       position: relative;
                       animation: modalSlideIn 0.3s ease;
                   ">
                       <h3 style="
                           color: var(--clr-danger);
                           margin-top: 0;
                           margin-bottom: 1rem;
                           font-size: 1.3rem;
                           display: flex;
                           align-items: center;
                           gap: 0.5rem;
                       "><span class="material-symbols-sharp">warning</span> Confirm Deletion</h3>
                       <p style="margin: 1rem 0; color: var(--clr-dark);">Are you sure you want to delete <strong>${itemName}</strong>? This action cannot be undone.</p>
                       <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                           <button id="cancelDeleteBtn" class="btn-warning" style="
                               padding: 0.6rem 1.2rem;
                               border-radius: var(--border-radius-1);
                               border: none;
                               cursor: pointer;
                               font-weight: 500;
                               transition: all 0.2s ease;
                           ">Cancel</button>
                           <button id="confirmDeleteBtn" class="btn-danger" style="
                               padding: 0.6rem 1.2rem;
                               border-radius: var(--border-radius-1);
                               border: none;
                               cursor: pointer;
                               font-weight: 500;
                               transition: all 0.2s ease;
                           ">Delete</button>
                       </div>
                   </div>
               </div>
           `;
           
           document.body.insertAdjacentHTML('beforeend', modalHtml);
           
           // Add event listeners
           document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
               document.getElementById('deleteConfirmModal').remove();
           });
           
           document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
               document.getElementById('deleteConfirmModal').remove();
               onDeleteCallback();
           });
       }

       function deleteFeedback(feedbackId, name, button) {
           showDeleteConfirmation(`feedback from ${name}`, function() {
               fetch('../includes/delete_feedback.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: 'feedback_id=' + encodeURIComponent(feedbackId)
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success) {
                       const row = button.closest('tr');
                       row.style.opacity = '0';
                       row.style.transition = 'opacity 0.3s ease';
                       setTimeout(() => {
                           row.remove();
                           // Check if table is now empty and add a message if needed
                           const tableBody = document.querySelector('table tbody');
                           if (tableBody.children.length === 0) {
                               tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No feedback yet.</td></tr>';
                           }
                       }, 300);
                   } else {
                       alert(data.message || 'Failed to delete feedback.');
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
                   alert('Error deleting feedback. Please try again.');
               });
           });
       }

       window.onclick = function(e) {
           const modal = document.getElementById('feedbackModal');
           if (e.target == modal) {
               closeModal();
           }
       }
   </script>

   
   <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>
