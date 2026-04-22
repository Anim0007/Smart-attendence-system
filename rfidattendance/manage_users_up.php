<div class="table-responsive-sm" style="max-height: 870px;">
  <table class="table">
    <thead class="table-primary">
      <tr>
        <th>Card UID</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody class="table-secondary">
      <?php
      require 'connectDB.php';

      // SHOW ONLY UNASSIGNED RFID CARDS
      $sql = "
        SELECT card_uid, status
        FROM rfid_cards
        WHERE student_id IS NULL
          AND status = 'active'
        ORDER BY card_uid DESC
      ";

      $res = mysqli_query($conn, $sql);

      if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
      ?>
          <tr>
            <td>
              <button
                type="button"
                class="select_btn"
                data-card="<?php echo htmlspecialchars($row['card_uid']); ?>">
                <?php echo htmlspecialchars($row['card_uid']); ?>
              </button>
            </td>
            <td>Unregistered</td>
          </tr>
      <?php
        }
      } else {
        echo "<tr><td colspan='2'>No unregistered cards</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>
