<?php if (isset($threads)): ?>
    <div class="page-heading"><div><p class="section-label">Student conversations</p><h1>Messages</h1></div></div>
    <?php if ($threads === []): ?>
        <div class="empty-state"><h2>No conversations yet</h2><p>Open an available listing and choose Message owner to start a conversation.</p><a class="button" href="<?= e(url()) ?>">Browse listings</a></div>
    <?php else: ?>
        <ul class="inbox-list">
            <?php foreach (array_slice($threads, 0, 30) as $thread): ?>
                <?php $sentAt = chatTime($thread['created_at']); ?>
                <li>
                    <a class="inbox-row" href="<?= e(url('chat', ['id' => $thread['listing_id'], 'buyer' => $thread['buyer_id']])) ?>">
                        <?php avatar((int) $thread['user_id'], $thread['full_name'], (bool) $thread['has_avatar']); ?>
                        <span class="inbox-copy">
                            <span><?= e($thread['full_name']) ?></span>
                            <strong><?= e($thread['title']) ?></strong>
                            <span class="muted inbox-preview"><?= e(mb_strimwidth($thread['body'], 0, 130, '…')) ?></span>
                            <?php if ($thread['deleted_at'] !== null): ?><small class="muted">Listing deleted</small><?php endif; ?>
                        </span>
                        <span class="inbox-meta">
                            <time class="muted" datetime="<?= e($sentAt->format(DATE_ATOM)) ?>"><?= e($sentAt->format('j M')) ?></time>
                            <?php if ($thread['cover_id'] !== null): ?>
                                <img class="chat-cover" src="<?= e(url('photo', ['id' => $thread['cover_id']])) ?>" width="56" height="56" alt="" loading="lazy">
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php $number = max(1, min(10001, (int) input($_GET, 'p'))); ?>
    <nav class="pagination" aria-label="Conversation pages">
        <?php if ($number > 1): ?><a class="button" href="<?= e(url('inbox', ['p' => $number - 1])) ?>">Previous page</a><?php endif; ?>
        <?php if (count($threads) > 30): ?><a class="button" href="<?= e(url('inbox', ['p' => $number + 1])) ?>">Next page</a><?php endif; ?>
    </nav>
<?php else: ?>
    <section class="conversation">
        <a class="back-link" href="<?= e(url('inbox')) ?>">Back to messages</a>
        <header class="chat-context">
            <div class="chat-participant">
                <?php avatar((int) $other['user_id'], $other['full_name'], (bool) ($other['has_avatar'] && $other['is_active'])); ?>
                <div><h1><?= e($other['full_name']) ?></h1><p class="muted">Listing conversation</p></div>
            </div>
            <div class="chat-item">
                <?php if ($cover !== null): ?>
                    <img class="chat-cover" src="<?= e(url('photo', ['id' => $cover['photo_id']])) ?>" width="56" height="56" alt="">
                <?php endif; ?>
                <div class="chat-item-name">
                    <p class="section-label"><?= e($listing['category_name']) ?></p>
                    <?php if ($showListingLink): ?>
                        <h2><a href="<?= e(url('view', ['id' => $listing['listing_id']])) ?>"><?= e($listing['title']) ?></a></h2>
                    <?php else: ?>
                        <h2><?= e($listing['title']) ?></h2>
                    <?php endif; ?>
                    <p class="muted"><?= e(LISTING_TYPES[$listing['listing_type']]) ?></p>
                </div>
                <span class="type-label"><?= $listing['deleted_at'] === null ? e(LISTING_STATUSES[$listing['status']]) : 'Deleted' ?></span>
            </div>
        </header>
        <div class="form-actions">
            <form method="get" action="/">
                <input type="hidden" name="page" value="chat">
                <input type="hidden" name="id" value="<?= e($listing['listing_id']) ?>">
                <input type="hidden" name="buyer" value="<?= e($buyerId) ?>">
                <button type="submit">Refresh messages</button>
            </form>
            <?php if ($older !== null): ?><a class="button" href="<?= e($threadUrl . '&before=' . $older) ?>">Older messages</a><?php endif; ?>
        </div>
        <?php if ($messages === []): ?><p class="empty-state">Ask about the item or arrange a pickup with the owner.</p><?php endif; ?>
        <ol class="message-list" aria-label="Conversation messages">
            <?php $lastDay = ''; ?>
            <?php foreach ($messages as $message): ?>
                <?php
                $sent = (int) $message['sender_id'] === $studentId;
                $sentAt = chatTime($message['created_at']);
                $day = $sentAt->format('Y-m-d');
                ?>
                <?php if ($day !== $lastDay): ?>
                    <li class="message-day"><time datetime="<?= e($day) ?>"><?= e($sentAt->format('j M Y')) ?></time></li>
                    <?php $lastDay = $day; ?>
                <?php endif; ?>
                <li class="message-row <?= $sent ? 'message-sent' : 'message-received' ?>">
                    <?php if (!$sent): ?><?php avatar((int) $other['user_id'], $other['full_name'], (bool) ($other['has_avatar'] && $other['is_active'])); ?><?php endif; ?>
                    <div class="message-bubble">
                        <span class="visually-hidden"><?= $sent ? 'You' : e($other['full_name']) ?></span>
                        <p><?= e($message['body']) ?></p>
                        <time datetime="<?= e($sentAt->format(DATE_ATOM)) ?>"><?= e($sentAt->format('g:i A')) ?></time>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <div id="latest"></div>
        <?php if ($open): ?>
            <form method="post" action="<?= e($threadUrl) ?>" class="form-panel stack-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div><label for="message">Message</label><textarea id="message" name="message" rows="3" required maxlength="2000"></textarea></div>
                <button class="button-primary" type="submit">Send message</button>
            </form>
        <?php else: ?>
            <p class="notice">This conversation is closed. Its message history is still available here.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
