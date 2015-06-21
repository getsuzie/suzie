<?php

namespace Suzie;

/**
 * Class Scheduler.
 */
class Scheduler
{
    /**
     * Array of events.
     *
     * @var array
     */
    protected $events = [];

    /**
     * Creates event.
     *
     * @param $callback
     *
     * @return Event
     */
    public function call($callback)
    {
        $this->events[] = $event = new Event($callback);

        return $event;
    }

    /**
     * Returns events.
     *
     * @return array
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Return due events.
     *
     * @return array
     */
    public function dueEvents()
    {
        return array_filter($this->events, function ($event) {
            return $event->isDue();
        });
    }

    /**
     * Fire any due events.
     */
    public function fire()
    {
        $dueEvents = $this->dueEvents();

        foreach ($dueEvents as $event) {
            $event->run();
        }
    }
}
