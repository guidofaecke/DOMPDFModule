<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Raymond J. Kolbe <rkolbe@gmail.com>
 * @copyright Copyright (c) 2012 University of Maine, 2016 Raymond J. Kolbe
 * @license	http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace DOMPDFModule\View\Strategy;

use DOMPDFModule\View\Model;
use DOMPDFModule\View\Renderer\PdfRenderer;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\View\ViewEvent;
use Traversable;

use function iterator_to_array;

class PdfStrategy implements ListenerAggregateInterface
{
    /**
     * @var callable[]
     */
    protected array $listeners = [];

    /**
     * @var PdfRenderer
     */
    protected PdfRenderer $renderer;

    /**
     * @param PdfRenderer $renderer
     */
    public function __construct(PdfRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Attach the aggregate to the specified event manager
     *
     * @param  EventManagerInterface $events
     * @param  int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RENDERER, [$this, 'selectRenderer'], $priority);
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RESPONSE, [$this, 'injectResponse'], $priority);
    }

    /**
     * Detach aggregate listeners from the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    /**
     * Detect if we should use the PdfRenderer based on model type
     *
     * @param  ViewEvent $event
     * @return null|PdfRenderer
     */
    public function selectRenderer(ViewEvent $event): ?PdfRenderer
    {
        $model = $event->getModel();

        if ($model instanceof Model\PdfModel) {
            return $this->renderer;
        }

        return null;
    }

    /**
     * Inject the response with the PDF payload and appropriate Content-Type header
     *
     * @param ViewEvent $event
     * @return void
     */
    public function injectResponse(ViewEvent $event): void
    {
        $renderer = $event->getRenderer();
        if ($renderer !== $this->renderer) {
            // Discovered renderer is not ours; do nothing
            return;
        }

        $result = $event->getResult();
        if (!\is_string($result)) {
            // No output to display. Good bye!
            return;
        }

        /** @var \Laminas\Http\Response|null $response */
        $response = $event->getResponse();

        if ($response === null) {
            return;
        }

        $response->setContent($result);

        $model   = $event->getModel();

        if ($model === null) {
            return;
        }

        $options = $model->getOptions();

        if ($options instanceof Traversable) {
            $optionsArray = iterator_to_array($options, true);
        } else {
            $optionsArray = $options;
        }

        /** @var string $fileName */
        $fileName = $optionsArray['fileName'];

        /** @var string $dispositionType */
        $dispositionType = $optionsArray['display'];

        if (!str_ends_with($fileName, '.pdf')) {
            $fileName .= '.pdf';
        }

        $headerValue = \sprintf('%s; filename="%s"', $dispositionType, $fileName);

        $headers = $response->getHeaders();
        $headers
            ->addHeaderLine('Content-Disposition', $headerValue)
            ->addHeaderLine('Content-Length', (string) strlen($result))
            ->addHeaderLine('Content-Type', 'application/pdf');
    }
}
