<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\PageCache\Test\Unit\Model\Layout;

class LayoutPluginTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\PageCache\Model\Layout\LayoutPlugin
     */
    protected $model;

    /**
     * @var \Magento\Framework\App\ResponseInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $responseMock;

    /**
     * @var \Magento\Framework\View\Layout|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $layoutMock;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $configMock;

    /**
     * @var \Magento\Framework\App\MaintenanceMode|\PHPUnit\Framework\MockObject\MockObject
     */
    private $maintenanceModeMock;

    protected function setUp()
    {
        $this->layoutMock = $this->getMockForAbstractClass(
            \Magento\Framework\View\Layout::class,
            [],
            '',
            false,
            true,
            true,
            ['isCacheable', 'getAllBlocks']
        );
        $this->responseMock = $this->createMock(\Magento\Framework\App\Response\Http::class);
        $this->configMock = $this->createMock(\Magento\PageCache\Model\Config::class);
        $this->maintenanceModeMock = $this->createMock(\Magento\Framework\App\MaintenanceMode::class);

        $this->model = new \Magento\PageCache\Model\Layout\LayoutPlugin(
            $this->responseMock,
            $this->configMock,
            $this->maintenanceModeMock
        );
    }

    /**
     * @param $cacheState
     * @param $layoutIsCacheable
     * @param $maintenanceModeIsEnabled
     *
     * @dataProvider afterGenerateXmlDataProvider
     */
    public function testAfterGenerateXml($cacheState, $layoutIsCacheable, $maintenanceModeIsEnabled)
    {
        $maxAge = 180;
        $result = 'test';

        $this->layoutMock->expects($this->once())->method('isCacheable')->will($this->returnValue($layoutIsCacheable));
        $this->configMock->expects($this->any())->method('isEnabled')->will($this->returnValue($cacheState));
        $this->maintenanceModeMock->expects($this->any())->method('isOn')
            ->will($this->returnValue($maintenanceModeIsEnabled));

        if ($layoutIsCacheable && $cacheState && !$maintenanceModeIsEnabled) {
            $this->configMock->expects($this->once())->method('getTtl')->will($this->returnValue($maxAge));
            $this->responseMock->expects($this->once())->method('setPublicHeaders')->with($maxAge);
        } else {
            $this->responseMock->expects($this->never())->method('setPublicHeaders');
        }
        $output = $this->model->afterGenerateXml($this->layoutMock, $result);
        $this->assertSame($result, $output);
    }

    /**
     * @return array
     */
    public function afterGenerateXmlDataProvider()
    {
        return [
            'Full_cache state is true, Layout is cache-able' => [true, true, false],
            'Full_cache state is true, Layout is not cache-able' => [true, false, false],
            'Full_cache state is false, Layout is not cache-able' => [false, false, false],
            'Full_cache state is false, Layout is cache-able' => [false, true, false],
            'Full_cache state is true, Layout is cache-able, Maintenance mode is enabled' => [true, true, true],
        ];
    }

    /**
     * @param $cacheState
     * @param $layoutIsCacheable
     * @param $expectedTags
     * @param $configCacheType
     * @param $ttl
     * @dataProvider afterGetOutputDataProvider
     */
    public function testAfterGetOutput($cacheState, $layoutIsCacheable, $expectedTags, $configCacheType, $ttl)
    {
        $html = 'html';
        $this->configMock->expects($this->any())->method('isEnabled')->will($this->returnValue($cacheState));
        $blockStub = $this->createPartialMock(
            \Magento\PageCache\Test\Unit\Block\Controller\StubBlock::class,
            ['getIdentities']
        );
        $blockStub->setTtl($ttl);
        $blockStub->expects($this->any())->method('getIdentities')->willReturn(['identity1', 'identity2']);
        $this->layoutMock->expects($this->once())->method('isCacheable')->will($this->returnValue($layoutIsCacheable));
        $this->layoutMock->expects($this->any())->method('getAllBlocks')->will($this->returnValue([$blockStub]));

        $this->configMock->expects($this->any())->method('getType')->will($this->returnValue($configCacheType));

        if ($layoutIsCacheable && $cacheState) {
            $this->responseMock->expects($this->once())->method('setHeader')->with('X-Magento-Tags', $expectedTags);
        } else {
            $this->responseMock->expects($this->never())->method('setHeader');
        }
        $output = $this->model->afterGetOutput($this->layoutMock, $html);
        $this->assertSame($output, $html);
    }

    /**
     * @return array
     */
    public function afterGetOutputDataProvider()
    {
        $tags = 'identity1,identity2';
        return [
            'Cacheable layout, Full_cache state is true' => [true, true, $tags, null, 0],
            'Non-cacheable layout' => [true, false, null, null, 0],
            'Cacheable layout with Varnish' => [true, true, $tags, \Magento\PageCache\Model\Config::VARNISH, 0],
            'Cacheable layout with Varnish, Full_cache state is false' => [
                false,
                true,
                $tags,
                \Magento\PageCache\Model\Config::VARNISH,
                0,
            ],
            'Cacheable layout with Varnish and esi' => [
                true,
                true,
                null,
                \Magento\PageCache\Model\Config::VARNISH,
                100,
            ],
            'Cacheable layout with Builtin' => [true, true, $tags, \Magento\PageCache\Model\Config::BUILT_IN, 0],
            'Cacheable layout with Builtin, Full_cache state is false' => [
                false,
                true,
                $tags,
                \Magento\PageCache\Model\Config::BUILT_IN,
                0,
            ],
            'Cacheable layout with Builtin and esi' => [
                true,
                false,
                $tags,
                \Magento\PageCache\Model\Config::BUILT_IN,
                100,
            ]
        ];
    }
}
