<?php

namespace QD\commerce\shipmondo\plugin;

abstract class Data
{
  // Shipmondo order statuses
  const STATUS_OPEN = 'open';
  const STATUS_PROCESSING = 'processing';
  const STATUS_PACKED = 'packed';
  const STATUS_CANCELLED = 'cancelled';
  const STATUS_ON_HOLD = 'on_hold';
  const STATUS_SENT = 'sent';
  const STATUS_PICKED_UP = 'picked_up';
  const STATUS_ARCHIVED = 'archived';
  const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
  const STATUS_RELEASED = 'released';
}
