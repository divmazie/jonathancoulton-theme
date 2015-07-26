FROM ubuntu:trusty

# install apache and other necessary progs
# adding bash here due to shellshock CVE-2014-6271, CVE-2014-7169
RUN DEBIAN_FRONTEND=noninteractive apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
		bash \
		flac \
		libav-tools \
		vorbis-tools \
		lame libmp3lame0

RUN mkdir -p /tmp/encodes

WORKDIR /tmp/encodes

ADD particleman.flac ./

RUN ls -la

RUN flac -d particleman.flac
RUN lame -V1 particleman.wav
RUN oggenc -b 224 particleman.wav
RUN avconv -i particleman.wav -acodec alac particleman.alac.m4a

# https://trac.ffmpeg.org/wiki/CompilationGuide/Ubuntu
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    autoconf automake build-essential libass-dev libfreetype6-dev \
    libsdl1.2-dev libtheora-dev libtool libva-dev libvdpau-dev libvorbis-dev libxcb1-dev libxcb-shm0-dev \
    libxcb-xfixes0-dev pkg-config texi2html zlib1g-dev

ENV SRCS_DIR /tmp/ffmpeg_sources

RUN mkdir -p ~/ffmpeg_sources ~/ffmpeg_build ~/bin

# yasm speeds up the resultant ffmpeg install
# If your repository offers a yasm package ≥ 1.2.0 then you can install that instead of compiling
# wget needed to get packages
# If your repository offers a libmp3lame-dev package ≥ 3.98.3 then you can install that instead of compiling
RUN  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    wget yasm libmp3lame-dev

# build libfdk-aac
RUN cd ~/ffmpeg_sources && \
    wget -O fdk-aac.tar.gz https://github.com/mstorsjo/fdk-aac/tarball/master && \
    tar xzvf fdk-aac.tar.gz && \
    cd mstorsjo-fdk-aac* && \
    autoreconf -fiv && \
    ./configure --prefix="$HOME/ffmpeg_build" --disable-shared && \
    make && make install && make distclean

# build ffmpeg itself
RUN cd ~/ffmpeg_sources && \
    wget http://ffmpeg.org/releases/ffmpeg-snapshot.tar.bz2 && \
    tar xjvf ffmpeg-snapshot.tar.bz2 && \
    cd ffmpeg && \
    PATH="$HOME/bin:$PATH" PKG_CONFIG_PATH="$HOME/ffmpeg_build/lib/pkgconfig" ./configure \
      --prefix="$HOME/ffmpeg_build" \
      --pkg-config-flags="--static" \
      --extra-cflags="-I$HOME/ffmpeg_build/include" \
      --extra-ldflags="-L$HOME/ffmpeg_build/lib" \
      --bindir="$HOME/bin" \
      --enable-gpl \
      --enable-libass \
      --enable-libfdk-aac \
      --enable-libfreetype \
      --enable-libmp3lame \
#      --enable-libopus \
      --enable-libtheora \
      --enable-libvorbis \
#      --enable-libvpx \
#      --enable-libx264 \
#      --enable-libx265 \
      --enable-nonfree && \
      PATH="$HOME/bin:$PATH" make && \
      make install && \
      make distclean

RUN cd ~/ffmpeg_sources/ffmpeg && hash -r

RUN ~/bin/ffmpeg -i particleman.wav -c:a libfdk_aac -b:a 128k particleman.fdkaac.m4a
RUN ~/bin/ffmpeg -i particleman.wav -acodec alac particleman.alac.ffmpeg.m4a

RUN mkdir tagging
WORKDIR /tmp/encodes/tagging

RUN ~/bin/ffmpeg -i ./../particleman.wav \
    -metadata title="Track #5 ALAC" \
    -metadata author="Unknown Artist" \
    -metadata composer="Composer Unknown" \
    -metadata album="Tracer Video Game Soundtrack" \
    -metadata year="1996" \
    -metadata track="5" \
    -metadata comment="This is redbook CD audio track #5 from the Windows 95 game \"Tracer\"" \
    -metadata genre="Game Soundtrack" \
    -metadata copyright="Copyright 1996 Future Endeavors, Inc." \
    -metadata description="Nifty techno background tune for a futuristic video game" \
    -acodec alac \
    alactag.m4a

RUN ~/bin/ffmpeg -i ./../particleman.wav \
    -metadata title="Track #5 AAC" \
    -metadata author="Unknown Artist" \
    -metadata composer="Composer Unknown" \
    -metadata album="Tracer Video Game Soundtrack" \
    -metadata year="1996" \
    -metadata track="5" \
    -metadata comment="This is redbook CD audio track #5 from the Windows 95 game \"Tracer\"" \
    -metadata genre="Game Soundtrack" \
    -metadata copyright="Copyright 1996 Future Endeavors, Inc." \
    -metadata description="Nifty techno background tune for a futuristic video game" \
    -c:a libfdk_aac -b:a 128k  \
    aactag.m4a

RUN ~/bin/ffmpeg -i ./../particleman.wav \
    -metadata title="Track #5 MP3" \
    -metadata author="Unknown Artist" \
    -metadata composer="Composer Unknown" \
    -metadata album="Tracer Video Game Soundtrack" \
    -metadata year="1996" \
    -metadata track="5" \
    -metadata comment="This is redbook CD audio track #5 from the Windows 95 game \"Tracer\"" \
    -metadata genre="Game Soundtrack" \
    -metadata copyright="Copyright 1996 Future Endeavors, Inc." \
    -metadata description="Nifty techno background tune for a futuristic video game" \
    -c:a libmp3lame -q:a 1  \
    mp3tag.mp3

RUN ~/bin/ffmpeg -i ./../particleman.wav \
    -metadata title="Track #5 MP3" \
    -metadata author="Unknown Artist" \
    -metadata composer="Composer Unknown" \
    -metadata album="Tracer Video Game Soundtrack" \
    -metadata year="1996" \
    -metadata track="5" \
    -metadata comment="This is redbook CD audio track #5 from the Windows 95 game \"Tracer\"" \
    -metadata genre="Game Soundtrack" \
    -metadata copyright="Copyright 1996 Future Endeavors, Inc." \
    -metadata description="Nifty techno background tune for a futuristic video game" \
    -c:a flac -q:a 0  \
    flactag.flac


RUN ls -la


CMD /root/bin/ffmpeg